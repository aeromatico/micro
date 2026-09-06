<?php namespace Aero\Sites\Models;

use Backend\Models\User as BackendUser;
use Backend\Models\UserRole;
use Model;
use Str;

/**
 * Invitación por correo/WhatsApp a colaborar en un tenant. Es sobre todo una
 * bitácora: el acceso real lo otorga TenantUser (que ResolvesCurrentTenant ya
 * sabe leer), y se crea en el mismo momento que la invitación — no hay un
 * paso de "aceptar" que bloquee el acceso, porque lo que se manda es
 * justamente el enlace para entrar y usarlo.
 *
 * Dos caminos según si el email ya tiene cuenta de backend:
 *  - Nuevo: se crea el Backend\Models\User (rol tenant_admin, sin contraseña
 *    utilizable todavía) y se reutiliza el flujo nativo de "reset password"
 *    de October (backend/auth/reset/{id}/{code}) como enlace de activación,
 *    en vez de construir una pantalla de registro propia.
 *  - Existente (p.ej. dueño de otro tenant): solo se agrega el TenantUser;
 *    ya tiene credenciales, se le manda el link de login normal.
 */
class TenantInvite extends Model
{
    public $table = 'aero_sites_tenant_invites';

    public $timestamps = true;

    protected $guarded = [];

    protected $dates = ['accepted_at'];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public static function send(Tenant $tenant, string $email, ?string $phone, string $role, ?BackendUser $invitedBy = null): static
    {
        $email = trim(strtolower($email));
        $existingUser = BackendUser::where('email', $email)->first();

        $invite = new static([
            'tenant_id'  => $tenant->id,
            'email'      => $email,
            'phone'      => $phone ?: null,
            'role'       => $role,
            'token'      => Str::random(40),
            'invited_by' => $invitedBy?->id,
        ]);

        if ($existingUser) {
            $tenant->addUser($existingUser, $role);

            $invite->backend_user_id = $existingUser->id;
            $invite->status          = 'accepted';
            $invite->accepted_at     = now();
            $invite->save();

            static::notify($tenant, $invite, $existingUser->full_name, \Backend::url('backend'));

            return $invite;
        }

        $user = static::createBackendUser($tenant, $email);
        $tenant->addUser($user, $role);

        $invite->backend_user_id = $user->id;
        $invite->status          = 'pending';
        $invite->save();

        $code = $user->getResetPasswordCode();
        $activationUrl = \Backend::url('backend/auth/reset/' . $user->id . '/' . $code);

        static::notify($tenant, $invite, $user->full_name, $activationUrl);

        return $invite;
    }

    protected static function createBackendUser(Tenant $tenant, string $email): BackendUser
    {
        $login = static::uniqueLogin($email);

        $user = new BackendUser();
        $user->first_name = $tenant->name;
        $user->last_name  = 'Colaborador';
        $user->login      = $login;
        $user->email      = $email;
        $password = Str::random(32);
        $user->password   = $password;
        $user->password_confirmation = $password;
        $user->is_activated = false;
        $user->save();

        $role = UserRole::where('code', 'tenant_admin')->first();
        if ($role) {
            $user->role_id = $role->id;
            $user->save();
        }

        return $user;
    }

    protected static function uniqueLogin(string $email): string
    {
        $base = Str::slug(explode('@', $email)[0]) ?: 'user';
        $login = $base;
        $suffix = 1;

        while (BackendUser::where('login', $login)->exists()) {
            $login = $base . '-' . (++$suffix);
        }

        return $login;
    }

    protected static function notify(Tenant $tenant, TenantInvite $invite, string $inviteeName, string $inviteUrl): void
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            return;
        }

        try {
            \Aero\Notify\Classes\Notify::fire('sites.user.invited', [
                'invitee_name' => $inviteeName,
                'role'         => $invite->role,
                'invite_url'   => $inviteUrl,
                'tenant_name'  => $tenant->name,
            ], [
                'tenant_id' => $tenant->id,
                'actor'     => [
                    'name'  => $inviteeName,
                    'email' => $invite->email,
                    'phone' => $invite->phone,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error("Aero.Sites: fallo notificando invitación de tenant {$tenant->id} a {$invite->email}: " . $e->getMessage());
        }
    }
}
