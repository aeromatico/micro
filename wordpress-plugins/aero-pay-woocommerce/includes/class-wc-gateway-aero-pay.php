<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pasarela "QR bancario" respaldada por `api/v1/pay/qr`
 * (Aero\Pay\Http\Controllers\Api\QrCodesController::store). Solo soporta el
 * flujo de QR bancario dinámico (BOB): la API pública de Pagos no persiste
 * `return_url`/`cancel_url` en el intento creado, así que el flujo de
 * redirect (PayPal, NOWPayments) no se puede completar desde afuera del
 * backend de Aero — ver Aero\Pay\Http\Controllers\Web\ReturnController.
 */
class WC_Gateway_Aero_Pay extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'aero_pay';
        $this->icon = '';
        $this->has_fields = false;
        $this->method_title = __('Aero Pay (QR bancario)', 'aero-pay-woocommerce');
        $this->method_description = __('Genera un QR de cobro bancario boliviano por cada pedido, usando tu cuenta de Aero Pay.', 'aero-pay-woocommerce');
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Activar/Desactivar', 'aero-pay-woocommerce'),
                'type'    => 'checkbox',
                'label'   => __('Activar Aero Pay', 'aero-pay-woocommerce'),
                'default' => 'no',
            ],
            'title' => [
                'title'       => __('Título', 'aero-pay-woocommerce'),
                'type'        => 'text',
                'description' => __('Lo que ve el cliente en el checkout.', 'aero-pay-woocommerce'),
                'default'     => __('Pago con QR', 'aero-pay-woocommerce'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Descripción', 'aero-pay-woocommerce'),
                'type'        => 'textarea',
                'default'     => __('Escanea el QR con tu app bancaria para pagar. La confirmación es automática.', 'aero-pay-woocommerce'),
            ],
            'api_url' => [
                'title'       => __('URL de la API de Aero Pay', 'aero-pay-woocommerce'),
                'type'        => 'text',
                'description' => __('El dominio de tu panel/tienda en Aero (Sites), sin /api al final. Ej: https://mitienda.micro.clouds.com.bo', 'aero-pay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'api_key' => [
                'title'       => __('API key', 'aero-pay-woocommerce'),
                'type'        => 'password',
                'description' => __('Backend de Aero → Pagos → API Tokens (o Aero.Api → API Keys), con los permisos qrbo.qr.create, qrbo.qr.read y qrbo.qr.cancel.', 'aero-pay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'webhook_secret' => [
                'title'       => __('Secreto del webhook', 'aero-pay-woocommerce'),
                'type'        => 'password',
                'description' => __('Debe ser exactamente el mismo valor que "Outbound webhook secret" en la cuenta bancaria usada, dentro del backend de Aero.', 'aero-pay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'bank_account_id' => [
                'title'       => __('Cuenta bancaria (opcional)', 'aero-pay-woocommerce'),
                'type'        => 'text',
                'description' => __('ID de la cuenta bancaria en Aero Pay a usar. Vacío = la cuenta de producción más antigua del tenant.', 'aero-pay-woocommerce'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'due_minutes' => [
                'title'       => __('Minutos para vencer el QR', 'aero-pay-woocommerce'),
                'type'        => 'number',
                'description' => __('Pasado este tiempo sin pagar, el pedido queda en espera y el QR se cancela automáticamente.', 'aero-pay-woocommerce'),
                'default'     => '30',
                'custom_attributes' => ['min' => '5', 'max' => '1440'],
            ],
            'webhook_help' => [
                'title'       => __('URL del webhook de confirmación', 'aero-pay-woocommerce'),
                'type'        => 'title',
                'description' => $this->webhook_help_text(),
            ],
        ];
    }

    private function webhook_help_text(): string
    {
        $url = Aero_Pay_Webhook_Handler::url();

        return sprintf(
            /* translators: %s: webhook URL */
            __('Configura esta URL como "Outbound webhook URL" en la cuenta bancaria de Aero Pay, para que los pagos se confirmen al instante: %s', 'aero-pay-woocommerce'),
            '<code>' . esc_html($url) . '</code>'
        );
    }

    public function is_valid_for_use(): bool
    {
        return $this->get_option('api_url') !== '' && $this->get_option('api_key') !== '';
    }

    public function get_api_client(): Aero_Pay_Api_Client
    {
        return new Aero_Pay_Api_Client($this->get_option('api_url'), $this->get_option('api_key'));
    }

    public function get_webhook_secret(): string
    {
        return (string) $this->get_option('webhook_secret');
    }

    /**
     * WooCommerce solo trabaja en la moneda de la tienda entera — no hay
     * forma de forzar BOB solo para este gateway. Se avisa en vez de
     * bloquear silenciosamente, porque una tienda podría vender en BOB con
     * total normalidad.
     */
    public function admin_options(): void
    {
        parent::admin_options();

        if (get_woocommerce_currency() !== 'BOB') {
            echo '<div class="notice notice-warning inline"><p>' .
                esc_html__('Tu tienda no factura en BOB. Aero Pay solo cobra en bolivianos; los pedidos en otra moneda no podrán usar este método.', 'aero-pay-woocommerce') .
                '</p></div>';
        }
    }

    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Pedido inválido.', 'aero-pay-woocommerce'), 'error');

            return ['result' => 'fail'];
        }

        if ($order->get_currency() !== 'BOB') {
            wc_add_notice(__('Aero Pay solo puede cobrar en bolivianos (BOB).', 'aero-pay-woocommerce'), 'error');

            return ['result' => 'fail'];
        }

        $payload = [
            'amount'             => (float) $order->get_total(),
            'currency'           => 'BOB',
            'description'        => sprintf(__('Pedido #%s', 'aero-pay-woocommerce'), $order->get_order_number()),
            'external_reference' => (string) $order->get_id(),
            'single_use'         => true,
        ];

        $bank_account_id = trim((string) $this->get_option('bank_account_id'));
        if ($bank_account_id !== '') {
            $payload['bank_account_id'] = (int) $bank_account_id;
        }

        $due_minutes = (int) $this->get_option('due_minutes', '30');
        if ($due_minutes > 0) {
            $payload['due_date'] = gmdate('Y-m-d', time() + ($due_minutes * 60));
        }

        $result = $this->get_api_client()->create_qr($payload);

        if (!$result['ok'] || empty($result['data']['id'])) {
            $message = $result['message'] ?? __('No se pudo generar el QR de cobro. Intenta de nuevo en unos minutos.', 'aero-pay-woocommerce');
            wc_add_notice($message, 'error');
            $order->add_order_note(sprintf('Aero Pay: fallo al crear QR (%s): %s', $result['error'] ?? 'error', $message));

            return ['result' => 'fail'];
        }

        $qr = $result['data'];

        $order->update_meta_data('_aero_pay_qr_id', $qr['id']);
        $order->update_meta_data('_aero_pay_qr_reference', $qr['internal_reference']);
        $order->save();

        $order->update_status('on-hold', __('Esperando confirmación de pago con Aero Pay.', 'aero-pay-woocommerce'));

        wc_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $order->get_checkout_order_received_url(),
        ];
    }
}
