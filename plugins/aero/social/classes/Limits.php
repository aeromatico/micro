<?php namespace Aero\Social\Classes;

use Aero\Social\Models\Account;

/**
 * Tope de cuentas conectadas según el plan del tenant (Sites → Planes →
 * Límites → `social_accounts`). Cuenta cada perfil activo: una página de
 * Facebook y su Instagram son 2. Sin Sites o sin límite = ilimitado.
 */
class Limits
{
    public const KEY = 'social_accounts';

    /** Solo tests: fn (?int $tenantId): ?int */
    public static ?\Closure $resolver = null;

    public static function max(?int $tenantId): ?int
    {
        if (self::$resolver) {
            return (self::$resolver)($tenantId);
        }

        if (!class_exists(\Aero\Sites\Classes\PlanLimits::class)) {
            return null;
        }

        return \Aero\Sites\Classes\PlanLimits::for($tenantId, self::KEY);
    }

    public static function used(?int $tenantId): int
    {
        return Account::forTenant($tenantId)->active()->count();
    }

    /** Cupos libres (null = ilimitado). */
    public static function remaining(?int $tenantId): ?int
    {
        $max = self::max($tenantId);

        return $max === null ? null : max(0, $max - self::used($tenantId));
    }
}
