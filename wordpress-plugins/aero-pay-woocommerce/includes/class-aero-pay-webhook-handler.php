<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Recibe el webhook saliente de Aero\Pay\Jobs\SendQrWebhookJob:
 * POST con header `X-Pay-Event: qr.<status>` y `X-Pay-Signature: sha256=<hmac>`
 * firmado con HMAC-SHA256 del body crudo, usando el "Outbound webhook secret"
 * de la cuenta bancaria. El body es `{ event, sent_at, data: <QrCode> }`.
 *
 * Se registra siempre (no solo con la pasarela activa) porque el tenant
 * puede configurar la URL en el backend de Aero antes de guardar los ajustes
 * acá — la validación real de "está todo configurado" pasa por comparar el
 * secreto guardado en WP contra la firma recibida.
 */
class Aero_Pay_Webhook_Handler
{
    public static function init(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('aero-pay/v1', '/webhook', [
                'methods'             => 'POST',
                'callback'            => [self::class, 'handle'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public static function url(): string
    {
        return rest_url('aero-pay/v1/webhook');
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = $gateways['aero_pay'] ?? null;

        if (!$gateway instanceof WC_Gateway_Aero_Pay) {
            return new WP_REST_Response(['error' => 'gateway_unavailable'], 503);
        }

        $secret = $gateway->get_webhook_secret();
        $raw_body = $request->get_body();
        $signature_header = (string) $request->get_header('x-pay-signature');

        if ($secret === '' || $signature_header === '') {
            return new WP_REST_Response(['error' => 'unauthenticated'], 401);
        }

        $expected = 'sha256=' . hash_hmac('sha256', $raw_body, $secret);

        if (!hash_equals($expected, $signature_header)) {
            return new WP_REST_Response(['error' => 'invalid_signature'], 401);
        }

        $payload = json_decode($raw_body, true);
        $qr = $payload['data'] ?? null;
        $event = $payload['event'] ?? '';

        if (!$qr || empty($qr['id'])) {
            return new WP_REST_Response(['error' => 'invalid_payload'], 422);
        }

        $order = self::find_order_for_qr((int) $qr['id']);

        if (!$order) {
            // No es un error: el mismo webhook puede llegar a varias tiendas
            // WordPress si comparten backend de Aero, o el QR pudo haberse
            // creado desde otro canal (shop propio, crm, etc).
            return new WP_REST_Response(['status' => 'ignored'], 200);
        }

        match ($event) {
            'qr.paid'      => self::mark_paid($order),
            'qr.cancelled', 'qr.expired' => self::mark_cancelled($order, $event),
            default        => null,
        };

        return new WP_REST_Response(['status' => 'ok'], 200);
    }

    private static function find_order_for_qr(int $qr_id): ?WC_Order
    {
        $orders = wc_get_orders([
            'limit'      => 1,
            'meta_key'   => '_aero_pay_qr_id',
            'meta_value' => $qr_id,
        ]);

        return $orders[0] ?? null;
    }

    public static function mark_paid(WC_Order $order): void
    {
        if ($order->has_status(['processing', 'completed'])) {
            return;
        }

        $order->payment_complete();
        $order->add_order_note(__('Aero Pay confirmó el pago del QR.', 'aero-pay-woocommerce'));
    }

    private static function mark_cancelled(WC_Order $order, string $event): void
    {
        if (!$order->has_status(['on-hold', 'pending'])) {
            return;
        }

        $reason = $event === 'qr.expired'
            ? __('El QR de Aero Pay venció sin pago.', 'aero-pay-woocommerce')
            : __('El QR de Aero Pay fue cancelado.', 'aero-pay-woocommerce');

        $order->update_status('cancelled', $reason);
    }
}
