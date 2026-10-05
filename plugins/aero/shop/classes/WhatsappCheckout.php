<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\Order;
use Aero\Shop\Models\ShopSettings;

/**
 * Checkout de la "Tienda para WhatsApp": arma el pedido como texto y lo
 * entrega por el modo elegido (api = enlace api.whatsapp.com hacia el número
 * de la tienda; market = envío desde una cuenta de Hello del tenant).
 */
class WhatsappCheckout
{
    /** Celular boliviano de 8 dígitos (6/7…) o con prefijo; solo dígitos. */
    public static function normalizePhone(?string $raw): ?string
    {
        $digits = ltrim((string) preg_replace('/\D+/', '', (string) $raw), '0');

        if (strlen($digits) === 8 && in_array($digits[0], ['6', '7'], true)) {
            $digits = '591' . $digits;
        }

        return (strlen($digits) >= 8 && strlen($digits) <= 15) ? $digits : null;
    }

    public static function buildMessage(Order $order): string
    {
        $order->loadMissing(['items', 'currency', 'shipping_address']);
        $cur = $order->currency;

        $lines = ["*Pedido {$order->order_number}*", ''];
        foreach ($order->items as $item) {
            $label = $item->variant_label_snapshot ? " ({$item->variant_label_snapshot})" : '';
            $lines[] = "• {$item->quantity}× {$item->product_name_snapshot}{$label} — " . $cur->format($item->line_total);
        }
        $lines[] = '';
        $lines[] = '*Total: ' . $cur->format($order->grand_total) . '*';

        if ($addr = $order->shipping_address) {
            $lines[] = '';
            if ($addr->address_line1) {
                $lines[] = '📍 ' . $addr->address_line1;
            }
            if ($addr->latitude !== null) {
                $lines[] = "https://www.google.com/maps?q={$addr->latitude},{$addr->longitude}";
            }
        }
        if ($order->customer_notes) {
            $lines[] = '';
            $lines[] = '📝 ' . $order->customer_notes;
        }

        return implode("\n", $lines);
    }

    public static function apiUrl(string $storeNumber, string $text): string
    {
        return 'https://api.whatsapp.com/send/?' . http_build_query([
            'phone' => $storeNumber, 'text' => $text, 'type' => 'phone_number', 'app_absent' => 0,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Market Api: manda el pedido al celular del cliente desde la cuenta elegida. Nunca lanza. */
    public static function sendViaHello(ShopSettings $settings, Order $order, string $phone): bool
    {
        if (!class_exists(\Aero\Hello\Classes\Hello::class) || !$settings->whatsapp_account_id) {
            return false;
        }

        try {
            $account = \Aero\Hello\Models\Account::forTenant((int) $settings->tenant_id)->find($settings->whatsapp_account_id);
            if (!$account) {
                return false;
            }

            $body = self::buildMessage($order) . "\n\n" . OrderService::publicUrl($order);
            \Aero\Hello\Classes\Hello::send($phone, $body, [
                'account_id' => $account->id, 'platform' => 'whatsapp', 'tenant_id' => $settings->tenant_id,
                'name' => $order->customer?->full_name,
            ]);

            return true;
        } catch (\Throwable $e) {
            \Log::error("Aero.Shop: no se pudo enviar el pedido {$order->id} por Hello: " . $e->getMessage());

            return false;
        }
    }
}
