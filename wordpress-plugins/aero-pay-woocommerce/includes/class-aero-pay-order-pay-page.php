<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pinta el QR y arranca el polling en la página "Pedido recibido" mientras
 * el pedido siga `on-hold` con un QR de Aero Pay pendiente. Una vez que el
 * webhook o el poller marcan el pedido como pagado, WooCommerce ya muestra
 * su thank-you normal (sin QR) en la siguiente carga — el JS de esta página
 * redirige él solo cuando detecta el cambio, sin que el cliente tenga que
 * refrescar a mano.
 */
class Aero_Pay_Order_Pay_Page
{
    public static function init(): void
    {
        add_action('woocommerce_thankyou_aero_pay', [self::class, 'render'], 5);
    }

    public static function render(int $order_id): void
    {
        $order = wc_get_order($order_id);

        if (!$order || $order->get_payment_method() !== 'aero_pay') {
            return;
        }

        $qr_id = $order->get_meta('_aero_pay_qr_id');
        $reference = $order->get_meta('_aero_pay_qr_reference');

        if (!$qr_id || !$reference) {
            return;
        }

        if ($order->has_status(['processing', 'completed'])) {
            echo '<div class="aero-pay-status aero-pay-status--paid">' .
                esc_html__('Pago confirmado. ¡Gracias!', 'aero-pay-woocommerce') .
                '</div>';

            return;
        }

        if (!$order->has_status(['on-hold', 'pending'])) {
            return;
        }

        $gateways = WC()->payment_gateways()->payment_gateways();
        $gateway = $gateways['aero_pay'] ?? null;

        if (!$gateway instanceof WC_Gateway_Aero_Pay) {
            return;
        }

        $image_url = $gateway->get_api_client()->public_image_url($reference);
        $status_url = Aero_Pay_Status_Poller::rest_url($order);

        wp_enqueue_style('aero-pay-checkout', AERO_PAY_WC_URL . 'assets/css/aero-pay.css', [], AERO_PAY_WC_VERSION);
        wp_enqueue_script('aero-pay-checkout', AERO_PAY_WC_URL . 'assets/js/aero-pay-checkout.js', [], AERO_PAY_WC_VERSION, true);
        wp_localize_script('aero-pay-checkout', 'aeroPayCheckout', [
            'statusUrl'    => $status_url,
            'pollMs'       => 4000,
            'redirectUrl'  => $order->get_checkout_order_received_url(),
            'paidMessage'  => __('Pago confirmado. Redirigiendo...', 'aero-pay-woocommerce'),
            'expiredMessage' => __('El QR venció. Genera un nuevo pedido para intentar de nuevo.', 'aero-pay-woocommerce'),
        ]);

        ?>
        <div class="aero-pay-box" id="aero-pay-box">
            <h2><?php esc_html_e('Escanea para pagar', 'aero-pay-woocommerce'); ?></h2>
            <img class="aero-pay-qr-image" src="<?php echo esc_url($image_url); ?>" alt="<?php esc_attr_e('Código QR de pago', 'aero-pay-woocommerce'); ?>" />
            <p class="aero-pay-amount"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></p>
            <p class="aero-pay-waiting" id="aero-pay-message"><?php esc_html_e('Esperando confirmación del pago...', 'aero-pay-woocommerce'); ?></p>
        </div>
        <?php
    }
}
