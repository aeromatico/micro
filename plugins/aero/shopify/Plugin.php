<?php namespace Aero\Shopify;

use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Cobro con QR bancario para tiendas Shopify, una cuenta por tenant. La tienda
 * declara un método de pago manual («QR Bolivia»); un webhook orders/create
 * emite el QR con aero/pay y, cuando el banco confirma, el pedido se marca
 * como pagado vía Admin API. Aero.Pay es dependencia blanda: sin él la
 * integración queda inerte.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Shopify',
            'description' => 'Cobra los pedidos de tu tienda Shopify con QR bancario (Aero.Pay) y márcalos como pagados automáticamente.',
            'author'      => 'Aero',
            'icon'        => 'icon-shopping-bag',
        ];
    }

    public function boot(): void
    {
        \View::addNamespace('aeroshopify', __DIR__ . '/views');

        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        // Integración blanda con Aero.Pay: reaccionar al cambio de estado del QR.
        Event::listen('aero.pay.qrStatusChanged', [\Aero\Shopify\Classes\PaymentListener::class, 'handle']);
    }

    public function registerPermissions(): array
    {
        return [
            'aero.shopify.use' => [
                'tab'   => 'Shopify',
                'label' => 'Usar Shopify: conectar su tienda y ver los pedidos cobrados con QR',
            ],
            'aero.shopify.superadmin' => [
                'tab'   => 'Shopify',
                'label' => 'Administrar Shopify: tiendas y pedidos de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        $perms = ['aero.shopify.use', 'aero.shopify.superadmin'];

        return [
            'shopify' => [
                'label'       => 'Shopify',
                'url'         => Backend::url('aero/shopify/stores'),
                'icon'        => 'icon-shopping-bag',
                'iconSvg'     => null,
                'permissions' => $perms,
                'order'       => 530,
                'sideMenu'    => [
                    'stores' => ['label' => 'Tiendas', 'icon' => 'icon-shopping-bag', 'url' => Backend::url('aero/shopify/stores'), 'permissions' => $perms],
                    'orders' => ['label' => 'Pedidos', 'icon' => 'icon-list', 'url' => Backend::url('aero/shopify/orders'), 'permissions' => $perms],
                ],
            ],
        ];
    }
}
