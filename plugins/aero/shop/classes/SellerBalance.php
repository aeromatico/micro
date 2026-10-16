<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\Order;

/**
 * Balance informativo de un comerciante del Marketplace. El cobro es directo a
 * su cuenta, así que no hay libro ni liquidaciones: todo se calcula desde sus
 * pedidos (cobrado, comisión del tenant, neto, por cobrar y reembolsos).
 */
class SellerBalance
{
    /**
     * @return array{collected: float, commission: float, net: float, pending: float, refunded: float, orders: int}
     */
    public static function for(int $tenantId, int $sellerId, ?string $from = null, ?string $to = null): array
    {
        $base = Order::forTenant($tenantId)->where('seller_id', $sellerId)
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        $paid = (clone $base)->whereNotNull('paid_at')->whereIn('status', ['paid', 'fulfilled']);

        return [
            'collected'  => round((float) (clone $paid)->sum('grand_total'), 2),
            'commission' => round((float) (clone $paid)->sum('commission_amount'), 2),
            'net'        => round((float) (clone $paid)->sum('seller_net'), 2),
            'pending'    => round((float) (clone $base)->whereIn('status', ['pending', 'awaiting_payment'])->sum('grand_total'), 2),
            'refunded'   => round((float) (clone $base)->where('status', 'refunded')->sum('grand_total'), 2),
            'orders'     => (clone $base)->count(),
        ];
    }
}
