<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\Order;

/**
 * Único punto por el que la tienda avisa de cambios de un pedido: dispara el
 * evento shop.order.* en Aero.Notify. Nunca lanza: un fallo de notificación no
 * puede romper la creación, el pago ni la cancelación de un pedido.
 *
 * Eventos: placed, paid, shipped (pedido 'fulfilled'), cancelled.
 */
class OrderNotifier
{
    public static function fire(Order $order, string $event, array $extra = []): void
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            return;
        }

        try {
            $order->loadMissing(['customer', 'currency', 'items']);
            $order->loadMissing('shipping_address');
            $customer = $order->customer;
            $addr = $order->shipping_address;
            $tenant   = \Aero\Sites\Models\Tenant::find($order->tenant_id);

            $context = [
                'order_number'  => $order->order_number,
                'customer_name' => $customer?->full_name ?? '',
                'total'         => number_format((float) $order->grand_total, 2),
                'currency'      => $order->currency?->code,
                'items'         => $order->items
                    ->map(fn ($i) => $i->quantity . '× ' . $i->product_name_snapshot)
                    ->implode(', '),
                'tenant_name'   => $tenant?->name,
                'url'           => \Aero\Shop\Classes\OrderService::publicUrl($order),
                'shipping_address' => $addr ? trim($addr->address_line1 . ', ' . $addr->city, ', ') : null,
                'location_url'  => $addr && $addr->latitude !== null ? "https://www.google.com/maps?q={$addr->latitude},{$addr->longitude}" : null,
            ] + $extra;

            if ($event === 'cancelled') {
                $context['reason'] = $order->cancel_reason;
            }

            \Aero\Notify\Classes\Notify::fire("shop.order.{$event}", $context, [
                'tenant_id' => (int) $order->tenant_id,
                'actor'     => [
                    'name'  => $customer?->full_name,
                    'email' => $customer?->email,
                    'phone' => $customer?->phone,
                ],
                'dedup_key' => "order:{$order->id}:{$event}",
            ]);
        } catch (\Throwable $e) {
            \Log::error("Aero.Shop: no se pudo notificar shop.order.{$event} del pedido {$order->id}: " . $e->getMessage());
        }
    }
}
