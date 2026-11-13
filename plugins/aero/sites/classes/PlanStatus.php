<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Tenant;

/**
 * Estado de vigencia del plan de un tenant, para el banner y «Mi plan».
 *
 *  - none:    prueba, sin vencimiento o plan sin renovación (no se muestra nada)
 *  - ok:      faltan más de WARN_DAYS días
 *  - soon:    vence en WARN_DAYS días o menos (incluye el mismo día)
 *  - overdue: ya venció; corre la gracia antes de suspender
 */
class PlanStatus
{
    public const WARN_DAYS = 7;
    public const GRACE_DAYS = 5;

    public static function for(Tenant $tenant): array
    {
        $expires = $tenant->plan_expires_at;

        if (!$expires || !in_array($tenant->billing_period, ['monthly', 'annual'], true)) {
            return ['state' => 'none', 'days' => null, 'expires' => null, 'grace_left' => null];
        }

        // Días de calendario completos hasta el vencimiento (negativo = vencido).
        $days = (int) now()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false);

        $state = $days < 0 ? 'overdue' : ($days <= static::WARN_DAYS ? 'soon' : 'ok');

        return [
            'state'      => $state,
            'days'       => $days,
            'expires'    => $expires,
            'grace_left' => $days < 0 ? max(0, static::GRACE_DAYS + $days) : null,
        ];
    }

    /** Frase corta para banner/correo: «vence hoy», «vence en 3 días», «venció hace 2 días». */
    public static function phrase(array $status): string
    {
        $d = $status['days'];

        return match (true) {
            $d === null => '',
            $d < 0      => 'venció hace ' . abs($d) . ' ' . (abs($d) === 1 ? 'día' : 'días'),
            $d === 0    => 'vence hoy',
            $d === 1    => 'vence mañana',
            default     => "vence en {$d} días",
        };
    }
}
