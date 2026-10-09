<?php namespace Aero\Shopify\Jobs;

use Aero\Shopify\Classes\ShopifyClient;
use Aero\Shopify\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkOrderPaidJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public function __construct(public int $orderLinkId)
    {
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600];
    }

    public function handle(): void
    {
        $link = Order::with('store')->find($this->orderLinkId);
        if (!$link || $link->paid_synced_at || !$link->store) {
            return;
        }

        try {
            (new ShopifyClient($link->store))->markOrderPaid($link->shopify_order_id);
        } catch (\Throwable $e) {
            $link->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
            throw $e;
        }

        $link->update(['status' => 'paid', 'error' => null, 'paid_synced_at' => now()]);
        \Event::fire('aero.shopify.orderPaid', [$link]);
    }

    public function failed(\Throwable $e): void
    {
        // El QR sí se cobró: se deja visible para que el comerciante marque el pedido a mano.
        Order::where('id', $this->orderLinkId)->update([
            'status' => 'error',
            'error'  => 'Cobrado pero no se pudo marcar en Shopify: ' . mb_substr($e->getMessage(), 0, 400),
        ]);
    }
}
