<?php namespace Aero\Credits\Classes;

/**
 * Aplica un "plan gratis por N periodos" a un tenant — usado tanto por
 * cupones como por invitaciones. Extiende `plan_expires_at` desde la fecha
 * de vencimiento vigente si todavía está en el futuro (así los regalos se
 * acumulan en vez de pisarse), o desde ahora si ya venció o nunca tuvo.
 * Al cambiar `plan_id` el propio `Tenant::afterSave()` de Aero.Sites ya
 * otorga los créditos del plan (Aero\Sites\Classes\PlanCredits::grant()) —
 * no hay que duplicar esa parte acá.
 */
class Grants
{
    public static function apply(\Aero\Sites\Models\Tenant $tenant, \Aero\Sites\Models\Plan $plan, string $periodUnit, int $periodCount): void
    {
        $periodCount = max(1, $periodCount);
        $months = $periodUnit === 'annual' ? $periodCount * 12 : $periodCount;

        $base = ($tenant->plan_expires_at && $tenant->plan_expires_at->isFuture())
            ? $tenant->plan_expires_at->copy()
            : now();

        $tenant->plan_id = $plan->id;
        $tenant->plan_price = 0;
        $tenant->billing_period = $periodUnit === 'annual' ? 'annual' : 'monthly';
        $tenant->plan_expires_at = $base->addMonths($months);
        $tenant->save();
    }
}
