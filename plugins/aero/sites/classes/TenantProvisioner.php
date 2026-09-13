<?php namespace Aero\Sites\Classes;

use Aero\Sites\Classes\Niches\NicheManager;
use Aero\Sites\Models\ApiToken;
use Aero\Sites\Models\Domain;
use Aero\Sites\Models\RootDomain;
use Aero\Sites\Models\Tenant;
use Backend;
use BackendAuth;
use Str;
use System\Models\SiteDefinition;

/**
 * Aprovisionamiento de un Tenant, en dos mitades independientes porque el
 * alta pública (SignupWizard) las corre en momentos distintos: el sitio se
 * arma apenas se confirma el pago (evento aero.qrbo.paymentReceived), y el
 * usuario administrador recién cuando el propio dueño completa sus datos en
 * el paso final del wizard. La creación desde el backend (Tenants::onCreate)
 * sigue corriendo ambas mitades una atrás de la otra, como siempre.
 */
class TenantProvisioner
{
    /**
     * Site definition + dominio primario + páginas/seo/contacto del niche +
     * token de API inicial. No toca usuarios — un tenant recién
     * "provisionSite()" todavía no tiene con qué iniciar sesión.
     */
    public function provisionSite(Tenant $tenant): string
    {
        $rootDomain = $tenant->rootDomain ?? RootDomain::find($tenant->root_domain_id);
        $primaryDomain = $tenant->handle . '.' . $rootDomain->domain;

        $site = $this->createSiteDefinition($tenant, $primaryDomain);
        $tenant->site_id = $site?->id;
        $tenant->save();

        Domain::create([
            'tenant_id'    => $tenant->id,
            'domain'       => $primaryDomain,
            'is_primary'   => true,
            'is_subdomain' => true,
        ]);

        $niche = app(NicheManager::class)->make($tenant->niche_type);
        $niche->provision($tenant);

        ['plain' => $plain, 'hashed' => $hashed] = ApiToken::generateToken();
        ApiToken::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Token inicial (solo lectura)',
            'token'     => $hashed,
            'abilities' => ['pages:read', 'seo:read', 'contact:submit'],
        ]);

        return $plain;
    }

    /**
     * Usuario backend (panel) + usuario frontend (RainLab, portal) del
     * tenant, con los datos reales que dio la persona (no un email/clave
     * inventados) — usado por el alta pública. Lanza excepción si el email
     * ya está en uso (login/backend duplicado), para que el caller la
     * traduzca a un mensaje de formulario.
     */
    public function provisionAdmin(Tenant $tenant, string $name, string $email, string $password): \Backend\Models\User
    {
        $user = BackendAuth::register([
            'first_name' => $name,
            'last_name'  => 'Admin',
            'login'      => $email,
            'email'      => $email,
            'password'   => $password,
            'password_confirmation' => $password,
        ]);

        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if ($role) {
            $user->role_id = $role->id;
        }
        $user->is_activated = true;
        $user->save();

        $tenant->backend_user_id = $user->id;
        $tenant->save();
        $tenant->addUser($user, 'admin');

        $this->createFrontendUser($tenant, $name, $email, $password);

        return $user;
    }

    /**
     * Alta desde el backend (superadmin): genera credenciales al vuelo y
     * corre ambas mitades juntas, igual que siempre. Devuelve las
     * credenciales para mostrarlas una única vez.
     */
    public function provisionTenant(Tenant $tenant): array
    {
        $apiToken = $this->provisionSite($tenant);

        $rootDomain = $tenant->rootDomain ?? RootDomain::find($tenant->root_domain_id);
        $password = Str::random(12);
        $email = $tenant->handle . '@' . $rootDomain->domain;

        $this->provisionAdmin($tenant, $tenant->name, $email, $password);

        return [
            'backend_url'    => Backend::baseUrl(),
            'email'          => $email,
            'password'       => $password,
            'api_token'      => $apiToken,
            'primary_domain' => $tenant->primary_domain,
        ];
    }

    protected function createSiteDefinition(Tenant $tenant, string $primaryDomain): ?object
    {
        if (!class_exists(SiteDefinition::class)) return null;

        $site = new SiteDefinition();
        $site->name               = $tenant->name;
        $site->code               = $tenant->handle;
        $site->is_enabled         = true;
        $site->theme              = 'microsites';
        $site->is_custom_url      = true;
        $site->app_url            = 'https://' . $primaryDomain;
        $site->is_host_restricted = true;
        $site->allow_hosts        = [['hostname' => $primaryDomain]];
        $site->is_enabled_edit    = true; // visible en admin/system/sites selector

        $site->save();
        return $site;
    }

    protected function createFrontendUser(Tenant $tenant, string $name, string $email, string $password): ?object
    {
        if (!class_exists(\RainLab\User\Models\User::class)) {
            return null;
        }

        if (\RainLab\User\Models\User::where('email', $email)->exists()) {
            return null;
        }

        try {
            $user = new \RainLab\User\Models\User;
            $user->name                  = $name;
            $user->email                 = $email;
            $user->password              = $password;
            $user->password_confirmation = $password;
            $user->is_activated          = true;
            $user->activated_at          = now();
            $user->save();

            return $user;
        } catch (\Exception $e) {
            \Log::error("Aero\\Sites: Failed to create frontend user for tenant {$tenant->id}: " . $e->getMessage());
            return null;
        }
    }
}
