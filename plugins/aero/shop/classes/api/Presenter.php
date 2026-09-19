<?php namespace Aero\Shop\Classes\Api;

use Aero\Shop\Classes\OrderService;
use Aero\Shop\Models\Order;

/** Forma pública de un pedido. Explícita, para que una columna interna nueva no se publique sola. */
class Presenter
{
    public static function order(Order $o): array
    {
        $qr = $o->payment_reference && class_exists(\Aero\Pay\Models\QrCode::class)
            ? \Aero\Pay\Models\QrCode::where('internal_reference', $o->payment_reference)->first() : null;

        return [
            'id' => $o->id, 'number' => $o->order_number, 'status' => $o->status,
            'currency' => $o->currency?->code, 'subtotal' => (float) $o->subtotal, 'total' => (float) $o->grand_total,
            'requires_shipping' => (bool) $o->requires_shipping,
            'customer' => $o->customer ? ['id' => $o->customer->id, 'name' => $o->customer->full_name, 'phone' => $o->customer->phone,
                'email' => str_ends_with($o->customer->email, '.invalid') ? null : $o->customer->email] : null,
            'items' => $o->items->map(fn ($i) => [
                'product_id' => $i->product_id, 'variant_id' => $i->product_variant_id, 'name' => $i->product_name_snapshot,
                'variant' => $i->variant_label_snapshot, 'sku' => $i->sku_snapshot, 'unit_price' => (float) $i->unit_price,
                'quantity' => (int) $i->quantity, 'line_total' => (float) $i->line_total,
            ])->all(),
            'payment' => [
                'method' => $o->payment_gateway?->name, 'driver' => $o->payment_gateway?->driver, 'instructions' => $o->payment_gateway?->instructions,
                'reference' => $o->payment_reference, 'qr_status' => $qr?->status,
                'qr_image_url' => $qr && $qr->qr_image ? url('/api/v1/pay/public/qr/' . $qr->internal_reference . '/image') : null,
            ],
            'order_url' => OrderService::publicUrl($o),
            'paid_at' => optional($o->paid_at)->toIso8601String(), 'created_at' => optional($o->created_at)->toIso8601String(),
        ];
    }
}
