<?php namespace Aero\Shop\Components;

use Aero\Shop\Classes\StorefrontContext;
use Aero\Shop\Models\Order;
use Cms\Classes\ComponentBase;

class OrderConfirmation extends ComponentBase
{
    public ?Order $order = null;
    public ?\Aero\Shop\Models\Currency $currency = null;
    public $qrCode = null;
    public string $timezone = 'America/La_Paz';

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
        if (!$this->loadOrder()) {
            return $this->controller->run('404');
        }
    }

    /**
     * En las llamadas AJAX de October no se ejecuta onRun(), así que cada
     * handler debe cargar el pedido por su cuenta: sin esto el fragmento
     * actualizado salía vacío y la tarjeta desaparecía hasta recargar la página.
     */
    protected function loadOrder(): bool
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return false;
        }

        $this->currency = StorefrontContext::currency();
        $this->timezone = StorefrontContext::settings()?->timezone ?: 'America/La_Paz';

        $this->order = Order::forTenant($tenant->id)
            ->where('access_token', $this->property('token'))
            ->with(['items', 'customer', 'payment_gateway', 'shipping_address', 'currency'])
            ->first();

        if (!$this->order) {
            return false;
        }

        $this->loadQrCode();

        return true;
    }

    /**
     * Solo tiene efecto con el driver pagos_qr sobre un proveedor con
     * getStatus() real (bancos QR con API, PayPal, NOWPayments): dispara una
     * consulta inmediata (misma lógica que el reconciliador de cron y el
     * botón "Consultar estado" del backend) para que el comprador no tenga
     * que esperar hasta el próximo minuto después de pagar. Una cuenta
     * 'qr_static' (correo, sin API) no tiene un getStatus() real que
     * consultar — su confirmación es manual, así que este botón solo
     * refresca la pantalla por si el vendedor ya lo marcó.
     */
    public function onCheckPaymentStatus()
    {
        if (!$this->loadOrder()) {
            return [];
        }

        if ($this->order?->payment_gateway?->driver === 'pagos_qr' && class_exists(\Aero\Pay\Classes\QrStatusReconciler::class)) {
            $qrCode = \Aero\Pay\Models\QrCode::where('internal_reference', $this->order->payment_reference)->first();
            if ($qrCode && $qrCode->status === 'pending' && ($qrCode->flow ?: 'qr_dynamic') !== 'qr_static') {
                app(\Aero\Pay\Classes\QrStatusReconciler::class)->reconcile($qrCode);
            }
        }

        $this->order->refresh();
        $this->loadQrCode();

        return ['#order-payment-status' => $this->renderPartial('@paymentStatus')];
    }

    /** Seguimiento en cocina: la página consulta cada pocos segundos. */
    public function onRefreshKitchen()
    {
        if (!$this->loadOrder()) {
            return [];
        }

        return ['#order-kitchen' => $this->renderPartial('@kitchenStatus')];
    }

    protected function loadQrCode(): void
    {
        if (
            $this->order?->payment_gateway?->driver === 'pagos_qr'
            && $this->order->payment_reference
            && class_exists(\Aero\Pay\Models\QrCode::class)
        ) {
            $this->qrCode = \Aero\Pay\Models\QrCode::where('internal_reference', $this->order->payment_reference)->first();
        }
    }
}
