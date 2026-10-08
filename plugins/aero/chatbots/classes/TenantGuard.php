<?php namespace Aero\Chatbots\Classes;

/**
 * Regla de aislamiento entre un bot y la cuenta que atiende, sin dependencias
 * (se prueba sola). Un bot de un cliente solo atiende cuentas de ESE cliente:
 * si no, leería los datos de un cliente y contestaría desde el número de otro.
 */
class TenantGuard
{
    /**
     * @param int|null $botTenant     cliente del bot (null = bot de plataforma, puede atender cualquiera)
     * @param int|null $accountTenant cliente efectivo de la cuenta (directo o por su perfil); null = sin cliente
     */
    public static function matches(?int $botTenant, ?int $accountTenant): bool
    {
        return $botTenant === null || ($accountTenant !== null && $botTenant === $accountTenant);
    }
}
