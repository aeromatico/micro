<?php namespace Aero\Shop\Components;

use Aero\Shop\Classes\StorefrontContext;
use Aero\Shop\Models\Order;
use Cms\Classes\ComponentBase;

class OrderConfirmation extends ComponentBase
{
    public ?Order $order = null;
    public ?\Aero\Shop\Models\Currency $currency = null;
    public $qrCode = null;

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

        $this->loadQrCode();
    }

    /**
     * Solo tiene efecto con el driver pagos_qr: dispara una consulta
     * inmediata al banco (misma lógica que el reconciliador de cron y el
     * botón "Consultar estado" del backend) para que el comprador no tenga
     * que esperar hasta 5 minutos después de pagar.
     */
    public function onCheckPaymentStatus()
    {
        if ($this->order?->payment_gateway?->driver === 'pagos_qr' && class_exists(\Aero\Qrbo\Classes\QrStatusReconciler::class)) {
            $qrCode = \Aero\Qrbo\Models\QrCode::where('internal_reference', $this->order->payment_reference)->first();
            if ($qrCode && $qrCode->status === 'pending') {
                app(\Aero\Qrbo\Classes\QrStatusReconciler::class)->reconcile($qrCode);
            }
        }

        $this->order?->refresh();
        $this->loadQrCode();

        return ['#order-payment-status' => $this->renderPartial('@paymentStatus')];
    }

    protected function loadQrCode(): void
    {
        if (
            $this->order?->payment_gateway?->driver === 'pagos_qr'
            && $this->order->payment_reference
            && class_exists(\Aero\Qrbo\Models\QrCode::class)
        ) {
            $this->qrCode = \Aero\Qrbo\Models\QrCode::where('internal_reference', $this->order->payment_reference)->first();
        }
    }
}
