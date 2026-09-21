<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Tenant;

/**
 * Otorga a un tenant los créditos que incluye su plan (Aero.Credits es
 * dependencia blanda). Una sola vez por tenant+plan+color: la clave de
 * idempotencia hace seguro llamarlo en cada activación o cambio de plan.
 */
class PlanCredits
{
    public static function grant(Tenant $tenant): int
    {
        if (!class_exists(\Aero\Credits\Classes\Credits::class) || !($plan = $tenant->plan)) {
            return 0;
        }

        // La prueba gratis no regala créditos (se podría repetir con handles nuevos).
        if ($tenant->billing_period === 'trial') {
            return 0;
        }

        $granted = 0;

        foreach ($plan->creditsByType() as $color => $amount) {
            try {
                $key = "plan:{$tenant->id}:{$plan->id}:{$color}";
                \Aero\Credits\Classes\Credits::topUp($tenant->id, $color, $amount, "Créditos del plan {$plan->name}", null, $key, 'plan_grant');
                $granted++;
            }
            catch (\Throwable $e) {
                // Un color inexistente/desactivado no debe romper la activación del tenant.
                \Log::error("Aero\\Sites: no se pudieron otorgar créditos {$color} del plan {$plan->code} al tenant {$tenant->id}: " . $e->getMessage());
            }
        }

        return $granted;
    }
}
