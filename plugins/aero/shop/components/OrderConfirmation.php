<?php namespace Aero\Shop\Components;

use Aero\Shop\Classes\StorefrontContext;
use Aero\Shop\Models\Order;
use Cms\Classes\ComponentBase;

class OrderConfirmation extends ComponentBase
{
    public ?Order $order = null;
    public ?\Aero\Shop\Models\Currency $currency = null;
    public $qrCode = null;
    /** Marketplace: los otros pedidos de la misma compra (uno por comerciante). */
    public $siblings = [];
    public string $timezone = 'America/La_Paz';
    /** Botón de emergencia: ¿se ofrece en este pedido? y su aviso vigente. */
    public bool $panicEligible = false;
    public ?\Aero\Shop\Models\PanicAlert $panic = null;
    /** Opinión del cliente: ¿se pide en este pedido? y la que ya dejó. */
    public bool $feedbackEligible = false;
    public ?\Aero\Shop\Models\Feedback $feedback = null;
    public string $feedbackMode = 'off';

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

        $this->siblings = $this->order->order_group
            ? Order::forTenant($tenant->id)->where('order_group', $this->order->order_group)
                ->where('id', '!=', $this->order->id)->with('seller')->orderBy('id')->get()
            : [];

        $this->loadQrCode();
        $this->loadPanic();
        $this->loadFeedback();

        return true;
    }

    protected function loadPanic(): void
    {
        $this->panicEligible = $this->order ? \Aero\Shop\Classes\PanicService::eligible($this->order) : false;
        $this->panic = $this->panicEligible ? \Aero\Shop\Classes\PanicService::current($this->order) : null;
    }

    /** La opinión se pide cuando el pedido ya está listo (cocina) o pagado/entregado, y la tienda la tiene activada. */
    protected function loadFeedback(): void
    {
        $this->feedbackMode = (string) (StorefrontContext::settings()?->feedback_mode ?: 'off');
        $this->feedback = $this->order ? \Aero\Shop\Models\Feedback::where('order_id', $this->order->id)->first() : null;

        $done = $this->order && !in_array($this->order->status, ['cancelled', 'refunded'], true)
            && ($this->order->kitchen_status
                ? in_array($this->order->kitchen_status, ['ready', 'delivered'], true)
                : in_array($this->order->status, ['paid', 'fulfilled'], true));

        $this->feedbackEligible = $this->feedbackMode !== 'off' && $done;
    }

    /** Guarda la opinión (una por pedido). La política de la tienda decide si queda interna, pública o pendiente. */
    public function onFeedback()
    {
        if (!$this->loadOrder()) {
            return [];
        }

        if ($this->feedbackEligible && !$this->feedback) {
            $rating = (int) post('rating');
            if ($rating < 1 || $rating > 5) {
                throw new \ApplicationException('Elige de 1 a 5 estrellas.');
            }

            $name = trim((string) post('author_name'));
            \Aero\Shop\Models\Feedback::create([
                'tenant_id'   => $this->order->tenant_id,
                'order_id'    => $this->order->id,
                'rating'      => $rating,
                'comment'     => mb_substr(trim((string) post('comment')), 0, 1000) ?: null,
                'author_name' => mb_substr($name, 0, 60) ?: null,
                'status'      => \Aero\Shop\Models\Feedback::statusForMode($this->feedbackMode),
            ]);
            $this->loadFeedback();
        }

        return ['#order-feedback' => $this->renderPartial('@feedback')];
    }

    /** Comida en el local: lo que el negocio quiere mostrar mientras se espera (WiFi e información). */
    public function waitingInfo(): array
    {
        if (!$this->order || !$this->order->order_type || in_array($this->order->kitchen_status, ['delivered'], true)
            || in_array($this->order->status, ['cancelled', 'refunded'], true)) {
            return [];
        }

        $cfg = StorefrontContext::settings()?->restaurant() ?? [];
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($cfg['info_text'] ?? '')))));
        $wifi = $this->order->order_type === 'dine_in' && ($cfg['wifi_password'] ?? '') !== ''
            ? ['name' => (string) ($cfg['wifi_name'] ?? ''), 'password' => (string) $cfg['wifi_password']]
            : null;

        return $lines || $wifi ? ['lines' => $lines, 'wifi' => $wifi] : [];
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

        return [
            '#order-kitchen'  => $this->renderPartial('@kitchenStatus'),
            '#order-panic'    => $this->renderPartial('@panic'),
            '#order-feedback' => $this->renderPartial('@feedback'),
        ];
    }

    /** Botón de emergencia: avisa al personal con el número de mesa. Nunca lanza: devuelve el estado a mostrar. */
    public function onPanic()
    {
        if (!$this->loadOrder()) {
            return [];
        }

        [$alert, $result] = \Aero\Shop\Classes\PanicService::raise($this->order, request()->ip());
        $this->loadPanic();

        return ['#order-panic' => $this->renderPartial('@panic', ['panicResult' => $result])];
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
