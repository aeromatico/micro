<?php namespace Aero\Notify\Classes;

use Aero\Notify\Classes\Support\Audiences;
use Aero\Sites\Models\Tenant;
use Aero\Sites\Models\TenantUser;
use Backend\Models\User as BackendUser;

/**
 * Traduce un código de audiencia (Audiences::*) en destinatarios concretos:
 * ['name' => ?, 'email' => ?, 'phone' => ?, 'user_id' => ?].
 *
 * 'actor' y 'adhoc' no se resuelven contra la base: los trae quien dispara el
 * evento en $options['actor'] / $options['adhoc'], porque son destinatarios
 * que Notify no conoce de antemano (un invitado sin cuenta, un remitente de
 * formulario, etc). 'tenant_user' se trata igual que 'tenant_admin' por ahora:
 * el gateway todavía no distingue colaboradores de clientes finales del
 * tenant (fase 2 pendiente).
 *
 * Backend\Models\User no tiene columna de teléfono, así que los canales que
 * la necesitan (whatsapp/sms) quedan sin dirección para las audiencias
 * resueltas por rol; se registran como 'skipped: no_address' en vez de fallar.
 */
class AudienceResolver
{
    public static function resolve(string $audience, int $tenantId, array $options = []): array
    {
        return match ($audience) {
            Audiences::SUPERADMIN => static::superadmins(),
            Audiences::TENANT_ADMIN, Audiences::TENANT_USER => static::tenantAdmins($tenantId),
            Audiences::ACTOR => static::fromOption($options['actor'] ?? null),
            Audiences::ADHOC => array_map([static::class, 'normalize'], (array) ($options['adhoc'] ?? [])),
            default => [],
        };
    }

    protected static function superadmins(): array
    {
        return BackendUser::where('is_superuser', true)
            ->get()
            ->map(fn (BackendUser $user) => static::fromBackendUser($user))
            ->all();
    }

    protected static function tenantAdmins(int $tenantId): array
    {
        if (!$tenantId) {
            return [];
        }

        $userIds = TenantUser::where('tenant_id', $tenantId)->pluck('user_id')->all();

        $tenant = Tenant::find($tenantId);
        if ($tenant?->backend_user_id) {
            $userIds[] = $tenant->backend_user_id;
        }

        return BackendUser::whereIn('id', array_unique($userIds))
            ->get()
            ->map(fn (BackendUser $user) => static::fromBackendUser($user))
            ->all();
    }

    protected static function fromBackendUser(BackendUser $user): array
    {
        return [
            'name'    => $user->full_name,
            'email'   => $user->email,
            'phone'   => null,
            'user_id' => $user->id,
        ];
    }

    protected static function fromOption($actor): array
    {
        if (!$actor) {
            return [];
        }

        return [static::normalize($actor)];
    }

    protected static function normalize(array $recipient): array
    {
        return [
            'name'    => $recipient['name'] ?? null,
            'email'   => $recipient['email'] ?? null,
            'phone'   => $recipient['phone'] ?? null,
            'user_id' => $recipient['user_id'] ?? null,
        ];
    }
}
