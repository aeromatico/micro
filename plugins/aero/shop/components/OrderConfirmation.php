<?php namespace Aero\Shop\Components;

use Aero\Shop\Classes\StorefrontContext;
use Aero\Shop\Models\Order;
use Cms\Classes\ComponentBase;

class OrderConfirmation extends ComponentBase
{
    public ?Order $order = null;
    public ?\Aero\Shop\Models\Currency $currency = null;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Shop Confirmación de Pedido',
            'description' => 'Página de gracias con resumen del pedido e instrucciones de pago.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'token' => [
                'title'   => 'Token',
                'type'    => 'string',
                'default' => ':token',
            ],
        ];
    }

    public function onRun()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return $this->controller->run('404');
        }

        $this->currency = StorefrontContext::currency();

        $this->order = Order::forTenant($tenant->id)
            ->where('access_token', $this->property('token'))
            ->with(['items', 'customer', 'payment_gateway', 'shipping_address', 'currency'])
            ->first();

        if (!$this->order) {
            return $this->controller->run('404');
        }
    }
}
