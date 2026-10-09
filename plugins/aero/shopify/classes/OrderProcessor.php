<?php namespace Aero\Shopify\Classes;

use Aero\Shopify\Models\Order;
use Aero\Shopify\Models\Store;

/**
 * Convierte el payload de orders/create en un QR de aero/pay. Idempotente por
 * (tienda, pedido): Shopify reintenta webhooks y puede entregarlos repetidos.
 */
class OrderProcessor
{
    public function process(Store $store, array $payload): ?Order
    {
        $shopifyId = (string) ($payload['id'] ?? '');
        if ($shopifyId === '' || !$store->matchesGateway((array) ($payload['payment_gateway_names'] ?? []))) {
            return null;
        }

        if ($existing = Order::where('store_id', $store->id)->where('shopify_order_id', $shopifyId)->first()) {
            return $existing;
        }

        $currency = strtoupper((string) ($payload['currency'] ?? 'BOB'));
        $link = Order::create([
            'store_id'         => $store->id,
            'tenant_id'        => $store->tenant_id,
            'shopify_order_id' => $shopifyId,
            'order_name'       => $payload['name'] ?? null,
            'customer_email'   => mb_strtolower((string) ($payload['email'] ?? $payload['contact_email'] ?? '')) ?: null,
            'amount'           => (float) ($payload['total_price'] ?? 0),
            'currency'         => $currency,
            'status'           => 'pending',
        ]);

        try {
            if (!class_exists(\Aero\Pay\Classes\QrIssuer::class)) {
                throw new \RuntimeException('Aero.Pay no está instalado.');
            }
            if (!in_array($currency, ['BOB', 'USD'], true)) {
                $link->update(['status' => 'skipped', 'error' => "Moneda no soportada: {$currency}"]);

                return $link;
            }

            $account = $store->bankAccount();
            if (!$account) {
                throw new \RuntimeException('La tienda no tiene una cuenta bancaria activa propia asignada.');
            }

            $qr = app(\Aero\Pay\Classes\QrIssuer::class)->issue(
                bankAccount: $account,
                amount: $link->amount,
                currency: $currency,
                description: 'Pedido ' . ($link->order_name ?: $shopifyId) . ' · ' . $store->name,
                externalReference: "shopify-{$store->id}-{$shopifyId}",
                origin: 'shopify',
                returnUrl: $payload['order_status_url'] ?? null,
                cancelUrl: $payload['order_status_url'] ?? null,
                tenantId: $store->tenant_id,
            );

            $link->update(['qr_code_id' => $qr->id]);
            $store->update(['last_error' => null]);
        } catch (\Throwable $e) {
            \Log::warning("Aero\\Shopify: no se pudo emitir el QR del pedido {$shopifyId} (tienda {$store->id}): " . $e->getMessage());
            $link->update(['status' => 'error', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            $store->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);
        }

        return $link;
    }
}
