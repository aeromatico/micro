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

        $plan = \Aero\Sites\Models\Plan::active()->find($coupon->plan_id);
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
