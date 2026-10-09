<?php namespace Aero\Shopify\Classes;

use Aero\Shopify\Jobs\MarkOrderPaidJob;
use Aero\Shopify\Models\Order;

/** Escucha aero.pay.qrStatusChanged; solo actúa sobre QR enlazados a un pedido de Shopify. */
class PaymentListener
{
    public function handle($qr): void
    {
        if (!$qr || ($qr->origin ?? null) !== 'shopify') {
            return;
        }

        $link = Order::where('qr_code_id', $qr->id)->first();
        if (!$link) {
            return;
        }

        match ($qr->status) {
            'paid'      => MarkOrderPaidJob::dispatch($link->id),
            'cancelled' => $link->update(['status' => 'cancelled']),
            'expired'   => $link->update(['status' => 'expired']),
            default     => null,
        };
    }
}
