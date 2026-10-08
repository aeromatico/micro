<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Respaldo del webhook: el navegador del cliente pregunta cada pocos
 * segundos si el QR ya se pagó, para tiendas que todavía no configuraron el
 * "Outbound webhook URL" en el backend de Aero, o mientras el webhook está
 * en camino. Idempotente con Aero_Pay_Webhook_Handler::mark_paid() — ambos
 * caminos pueden llegar primero.
 */
class Aero_Pay_Status_Poller
{
    public static function init(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('aero-pay/v1', '/orders/(?P<order_id>\d+)/status', [
                'methods'             => 'GET',
                'callback'            => [self::class, 'handle'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'order_id' => ['required' => true],
                    'key'      => ['required' => true],
                ],
            ]);
        });
    }

    public static function rest_url(WC_Order $order): string
    {
        return add_query_arg(
            'key',
            $order->get_order_key(),
            rest_url('aero-pay/v1/orders/' . $order->get_id() . '/status')
        );
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $order = wc_get_order((int) $request->get_param('order_id'));

        // La order key hace de "contraseña" de este pedido puntual — el
        // endpoint es público (el navegador del comprador no tiene sesión de
        // WP), pero sin ella cualquiera podría espiar el estado de pedidos
        // ajenos con solo probar IDs consecutivos.
        if (!$order || !hash_equals($order->get_order_key(), (string) $request->get_param('key'))) {
            return new WP_REST_Response(['error' => 'not_found'], 404);
        }

        if ($order->has_status(['processing', 'completed'])) {
            return new WP_REST_Response(['status' => 'paid'], 200);
        }

        $qr_id = $order->get_meta('_aero_pay_qr_id');
        if (!$qr_id || $order->get_payment_method() !== 'aero_pay') {
            return new WP_REST_Response(['status' => 'unknown'], 200);
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = $gateways['aero_pay'] ?? null;

        if (!$gateway instanceof WC_Gateway_Aero_Pay) {
            return new WP_REST_Response(['status' => 'unknown'], 200);
        }

        $result = $gateway->get_api_client()->get_qr((int) $qr_id);

        if (!$result['ok'] || empty($result['data']['status'])) {
            return new WP_REST_Response(['status' => 'unknown'], 200);
        }

        $remote_status = $result['data']['status'];

        if ($remote_status === 'paid') {
            Aero_Pay_Webhook_Handler::mark_paid($order);
        } elseif (in_array($remote_status, ['cancelled', 'expired'], true) && $order->has_status('on-hold')) {
            $order->update_status('cancelled', __('El QR de Aero Pay venció o fue cancelado sin pago.', 'aero-pay-woocommerce'));
        }

        return new WP_REST_Response(['status' => $remote_status], 200);
    }
}
