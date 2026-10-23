<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Member;

/**
 * Quién es «miembro» en el sitio: un usuario con sesión vinculado a un Member DE ESTA IGLESIA y con estado
 * activo o nuevo. Cualquier otra combinación es visitante (falla cerrado).
 */
class PortalAccess
{
    public static function memberFor($user, ?int $tenantId): ?Member
    {
        if (!$user || !$tenantId) {
            return null;
        }

        return Member::forTenant($tenantId)->where('user_id', $user->id)->whereIn('status', ['active', 'new'])->first();
    }

    public static function isMember($user, ?int $tenantId): bool
    {
        return (bool) self::memberFor($user, $tenantId);
    }
}
