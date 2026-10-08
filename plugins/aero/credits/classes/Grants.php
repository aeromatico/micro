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

        $base = ($tenant->plan_expires_at && $tenant->plan_expires_at->isFuture())
            ? $tenant->plan_expires_at->copy()
            : now();

        $tenant->plan_id = $plan->id;
        $tenant->plan_price = 0;

        // 'daily' = periodo corto (p.ej. los 7 días del Trial); los otros dos
        // son los de siempre.
        if ($periodUnit === 'daily') {
            $tenant->billing_period = 'trial';
            $tenant->plan_expires_at = $base->addDays($periodCount);
        } else {
            $tenant->billing_period = $periodUnit === 'annual' ? 'annual' : 'monthly';
            $tenant->plan_expires_at = $base->addMonths($periodUnit === 'annual' ? $periodCount * 12 : $periodCount);
        }
        $tenant->save();
    }

    /** "7 días", "1 mes", "2 años" */
    public static function periodLabel(string $unit, int $count): string
    {
        $count = max(1, $count);

        return match ($unit) {
            'daily'  => $count . ($count === 1 ? ' día' : ' días'),
            'annual' => $count . ($count === 1 ? ' año' : ' años'),
            default  => $count . ($count === 1 ? ' mes' : ' meses'),
        };
    }
}
