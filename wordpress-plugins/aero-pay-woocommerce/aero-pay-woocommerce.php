<?php
/**
 * Plugin Name: Aero Pay para WooCommerce
 * Plugin URI: https://panel.market.com.bo
 * Description: Cobra con QR bancario boliviano (BNB, Banco Económico y otros) directo en el checkout de WooCommerce, usando tu cuenta de Aero Pay.
 * Version: 1.0.0
 * Author: Aero
 * Author URI: https://panel.market.com.bo
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: aero-pay-woocommerce
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AERO_PAY_WC_VERSION', '1.0.0');
define('AERO_PAY_WC_FILE', __FILE__);
define('AERO_PAY_WC_DIR', plugin_dir_path(__FILE__));
define('AERO_PAY_WC_URL', plugin_dir_url(__FILE__));

/**
 * Todo el plugin depende de que exista la clase WC_Payment_Gateway, que solo
 * se registra si WooCommerce ya cargó. Sin este guard, activar el plugin en
 * un WordPress sin WooCommerce (o antes de que cargue, según el orden de
 * plugins) tira un fatal error blanco de pantalla.
 */
add_action('plugins_loaded', 'aero_pay_wc_init', 11);

function aero_pay_wc_init(): void
{
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>' .
                esc_html__('Aero Pay para WooCommerce necesita que WooCommerce esté instalado y activo.', 'aero-pay-woocommerce') .
                '</p></div>';
        });

        return;
    }

    require_once AERO_PAY_WC_DIR . 'includes/class-aero-pay-api-client.php';
    require_once AERO_PAY_WC_DIR . 'includes/class-wc-gateway-aero-pay.php';
    require_once AERO_PAY_WC_DIR . 'includes/class-aero-pay-order-pay-page.php';
    require_once AERO_PAY_WC_DIR . 'includes/class-aero-pay-webhook-handler.php';
    require_once AERO_PAY_WC_DIR . 'includes/class-aero-pay-status-poller.php';

    Aero_Pay_Order_Pay_Page::init();
    Aero_Pay_Webhook_Handler::init();
    Aero_Pay_Status_Poller::init();

    add_filter('woocommerce_payment_gateways', function (array $gateways): array {
        $gateways[] = 'WC_Gateway_Aero_Pay';

        return $gateways;
    });

    add_filter('plugin_action_links_' . plugin_basename(AERO_PAY_WC_FILE), function (array $links): array {
        $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=aero_pay');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Configuración', 'aero-pay-woocommerce') . '</a>');

        return $links;
    });

    load_plugin_textdomain('aero-pay-woocommerce', false, dirname(plugin_basename(AERO_PAY_WC_FILE)) . '/languages');
}

/**
 * Declara compatibilidad con HPOS (High-Performance Order Storage) — sin
 * esto WooCommerce muestra un aviso de incompatibilidad en Estado > Salud del
 * sitio y, en versiones futuras, podría desactivar el plugin en tiendas con
 * HPOS habilitado.
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            AERO_PAY_WC_FILE,
            true
        );
    }
});
