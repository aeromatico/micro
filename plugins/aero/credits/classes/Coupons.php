<?php namespace Aero\Credits\Classes;

use Aero\Credits\Models\CreditCoupon;
use DB;

/**
 * Cupones públicos: código creado por el superadmin (sin dueño), canjeable
 * por cualquiera al darse de alta. `preview()` valida sin consumir (para
 * poder decidir el flujo de alta antes de crear el tenant); `consume()`
 * aplica el regalo sobre un tenant ya creado.
 */
class Coupons
{
    /** @return array{type:string,model:CreditCoupon,plan:\Aero\Sites\Models\Plan,period_unit:string,period_count:int}|null */
    public static function preview(string $code): ?array
    {
        if (!class_exists(\Aero\Sites\Models\Plan::class)) {
            return null;
        }

        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }

        $coupon = CreditCoupon::redeemable()->where('code', $code)->first();
        if (!$coupon || !$coupon->plan_id) {
            return null;
        }

        $plan = \Aero\Sites\Models\Plan::find($coupon->plan_id);
        if (!$plan) {
            return null;
        }

        return [
            'type'         => 'coupon',
            'model'        => $coupon,
            'plan'         => $plan,
            'period_unit'  => $coupon->period_unit,
            'period_count' => (int) $coupon->period_count,
        ];
    }

    /**
     * Canje por un tenant que ya existe (autoservicio). A diferencia del alta,
     * aquí se cuida no pisar lo que ya tiene: nunca el Trial (solo sitios
     * nuevos), un solo canje por tenant y cupón, y solo si el plan del regalo
     * es el mismo que ya tiene o si su plan ya venció / no tiene.
     *
     * @throws \RuntimeException con mensaje apto para mostrar
     */
    public static function redeemForTenant(string $code, \Aero\Sites\Models\Tenant $tenant): array
    {
        $redemption = static::preview($code);
        if (!$redemption) {
            throw new \RuntimeException('Ese código no es válido o ya venció.');
        }

        $coupon = $redemption['model'];
        $plan = $redemption['plan'];

        if ((int) $plan->trial_days > 0) {
            throw new \RuntimeException('Ese código es de prueba y solo sirve al crear un sitio nuevo.');
        }

        $hasCurrentPlan = $tenant->plan_id && $tenant->plan_expires_at && $tenant->plan_expires_at->isFuture();
        if ($hasCurrentPlan && (int) $tenant->plan_id !== (int) $plan->id) {
            $current = \Aero\Sites\Models\Plan::find($tenant->plan_id)->name ?? 'actual';
            throw new \RuntimeException("Este código es del plan {$plan->name} y tu plan vigente es {$current}. Podrás canjearlo cuando el tuyo venza.");
        }

        DB::transaction(function () use ($coupon, $tenant) {
            $exists = DB::table('aero_credits_coupon_redemptions')
                ->where('coupon_id', $coupon->id)->where('tenant_id', $tenant->id)->lockForUpdate()->exists();

            if ($exists) {
                throw new \RuntimeException('Ya canjeaste este código.');
            }

            static::consume($coupon, $tenant);

            DB::table('aero_credits_coupon_redemptions')->insert([
                'coupon_id' => $coupon->id, 'tenant_id' => $tenant->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return $redemption;
    }

    public static function consume(CreditCoupon $coupon, \Aero\Sites\Models\Tenant $tenant): void
    {
        DB::transaction(function () use ($coupon, $tenant) {
            $locked = CreditCoupon::whereKey($coupon->id)->lockForUpdate()->first();

            if (!$locked || !$locked->is_active) {
                throw new \RuntimeException('Cupón no válido.');
            }

            if ($locked->max_redemptions !== null && $locked->times_redeemed >= $locked->max_redemptions) {
                throw new \RuntimeException('Cupón agotado.');
            }

            if ($locked->expires_at && $locked->expires_at->isPast()) {
                throw new \RuntimeException('Cupón vencido.');
            }

            $locked->increment('times_redeemed');

            $plan = \Aero\Sites\Models\Plan::find($locked->plan_id);
            if ($plan) {
                Grants::apply($tenant, $plan, $locked->period_unit, (int) $locked->period_count);
            }
        });
    }
}
