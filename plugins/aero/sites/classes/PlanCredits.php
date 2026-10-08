<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Tenant;

/**
 * Otorga a un tenant los créditos que incluye su plan (Aero.Credits es
 * dependencia blanda). Idempotente por tenant+plan+color (más el ciclo,
 * si se pasa uno): sin $cycleKey, una sola vez de por vida — así llama
 * Tenant::afterSave() al activar o cambiar de plan. Con $cycleKey (p.ej.
 * "renewal:{id}"), una vez por ciclo — así lo llama el puente de pago de
 * cada renovación (Plugin::bootPayRenewalBridge), sin duplicar si el
 * webhook del banco reintenta.
 */
class PlanCredits
{
    public static function grant(Tenant $tenant, ?string $cycleKey = null): int
    {
        if (!class_exists(\Aero\Credits\Classes\Credits::class) || !($plan = $tenant->plan)) {
            return 0;
        }

        // La prueba gratis no regala créditos (se podría repetir con handles nuevos).
        if ($tenant->billing_period === 'trial') {
            return 0;
        }

        $granted = 0;

        foreach ($plan->creditsConfig() as $row) {
            $color = $row['credit_type'];
            $amount = $row['amount'];

            if (!$color || $amount <= 0) {
                continue;
            }

            try {
                $key = $cycleKey
                    ? "plan:{$tenant->id}:{$plan->id}:{$color}:{$cycleKey}"
                    : "plan:{$tenant->id}:{$plan->id}:{$color}";
                // mode=expiring: el regalo vence si no se usa antes del
                // vencimiento vigente del tenant (plan_expires_at ya está
                // actualizado a esta altura, tanto en la activación como en
                // cada renovación — ver Plugin::bootPayRenewalBridge).
                $expiresAt = $row['mode'] === 'expiring' ? $tenant->plan_expires_at : null;
                \Aero\Credits\Classes\Credits::topUp($tenant->id, $color, $amount, "Créditos del plan {$plan->name}", null, $key, 'plan_grant', [], $expiresAt);
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
