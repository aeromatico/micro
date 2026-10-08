<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\Order;
use Aero\Shop\Models\ShopSettings;

/**
 * Tiempo estimado de un pedido de restaurante.
 *
 *   total = preparación (plato más lento)
 *         + carga de cocina (minutos por cada pedido activo por encima de la capacidad)
 *         + modo ocupado (minutos extra manuales)
 *         + reparto (solo delivery: asignar repartidor + trayecto)
 *
 * No se muestra al cliente al hacer el pedido: cocina lo propone al aceptar
 * (se puede ajustar) y recién entonces queda como hora prometida.
 */
class EtaCalculator
{
    public static function minutes(Order $order, ShopSettings $settings): int
    {
        $cfg = $settings->restaurant();
        $order->loadMissing('items.product');

        $prep = (int) $order->items->map(fn ($i) => (int) ($i->product?->prep_minutes))->max();
        if ($prep <= 0) {
            $prep = (int) $cfg['default_prep'];
        }

        // Pedidos que la cocina todavía no terminó (sin contar este).
        $queue = Order::forTenant($order->tenant_id)->where('status', '!=', 'cancelled')
            ->whereIn('kitchen_status', ['new', 'preparing'])->where('id', '!=', $order->id)->count();
        $load = max(0, $queue - (int) $cfg['capacity']) * (int) $cfg['load_minutes'];

        $busy = !empty($cfg['busy']) ? (int) $cfg['busy_extra'] : 0;
        $delivery = $order->order_type === 'delivery' ? (int) $cfg['delivery_extra'] : 0;

        return self::roundUp($prep + $load + $busy + $delivery);
    }

    /** Redondea al múltiplo de 5 superior: "25 min" transmite más confianza que "23 min". */
    public static function roundUp(int $minutes): int
    {
        return max(5, (int) (ceil($minutes / 5) * 5));
    }

    /** Hora prometida al aceptar: la programada, o ahora + minutos. */
    public static function promisedAt(Order $order, int $minutes): \Carbon\Carbon
    {
        return $order->scheduled_for ? $order->scheduled_for->copy() : now()->addMinutes($minutes);
    }
}
