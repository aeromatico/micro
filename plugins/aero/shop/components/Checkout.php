<?php namespace Aero\Shop\Components;

use Aero\Shop\Classes\CartService;
use Aero\Shop\Classes\Exceptions\InsufficientStockException;
use Aero\Shop\Classes\InventoryService;
use Aero\Shop\Classes\OrderNumberGenerator;
use Aero\Shop\Classes\StorefrontContext;
use Aero\Shop\Models\Address;
use Aero\Shop\Models\Customer;
use Aero\Shop\Models\Order;
use Aero\Shop\Models\OrderItem;
use Aero\Shop\Models\PaymentGateway;
use Auth;
use Cms\Classes\ComponentBase;
use Db;
use Illuminate\Support\Facades\Validator;
use Redirect;

class Checkout extends ComponentBase
{
    public array $lines = [];
    public float $subtotal = 0;
    public bool $requiresShipping = false;
    public ?\Aero\Shop\Models\Currency $currency = null;
    public $paymentGateways = null;
    public ?\RainLab\User\Models\User $authUser = null;
    public bool $whatsappStore = false;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Shop Checkout',
            'description' => 'Checkout de una sola página: contacto, envío (si aplica) y método de pago.',
        ];
    }

    public function onRun()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant || !StorefrontContext::isEnabled()) {
            return $this->controller->run('404');
        }

        $this->currency = StorefrontContext::currency();
        $this->whatsappStore = (bool) StorefrontContext::settings()?->isWhatsappStore();

        $cart = new CartService($tenant->id);
        $this->lines = $cart->lines();
        $this->subtotal = $cart->subtotal();
        $this->requiresShipping = $cart->requiresShipping();

        $this->paymentGateways = PaymentGateway::forTenant($tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if (class_exists(\RainLab\User\Models\User::class)) {
            $this->authUser = Auth::user();
        }
    }

    public function onPlaceOrder()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return $this->errorResponse('Tienda no encontrada.');
        }

        $settings = StorefrontContext::settings();
        if (!$settings || !$settings->is_enabled || !$settings->base_currency_id) {
            return $this->errorResponse('La tienda no está disponible en este momento.');
        }

        $cart = new CartService($tenant->id);
        $lines = $cart->lines();
        if (!$lines) {
            return $this->errorResponse('Tu carrito está vacío.');
        }

        if ($settings->isWhatsappStore()) {
            return $this->placeWhatsappOrder($tenant, $settings, $cart, $lines);
        }

        $data = post();
        $requiresShipping = $cart->requiresShipping();

        $rules = [
            'first_name'          => 'required|min:2|max:100',
            'last_name'           => 'nullable|max:100',
            'email'               => 'required|email|max:255',
            'phone'               => 'required|max:30',
            'payment_gateway_id'  => 'required|exists:aero_shop_payment_gateways,id',
            'customer_notes'      => 'nullable|max:1000',
        ];

        if ($requiresShipping) {
            $rules = array_merge($rules, [
                'address_line1'  => 'required|max:200',
                'address_line2'  => 'nullable|max:200',
                'city'           => 'required|max:100',
                'state_province' => 'nullable|max:100',
                'postal_code'    => 'nullable|max:20',
                'country_code'   => 'required|size:2',
            ]);
        }

        $validator = Validator::make($data, $rules, [
            'first_name.required' => 'El nombre es obligatorio.',
            'email.required'      => 'El email es obligatorio.',
            'email.email'         => 'El email no es válido.',
            'phone.required'      => 'El teléfono es obligatorio.',
            'payment_gateway_id.required' => 'Selecciona un método de pago.',
            'address_line1.required' => 'La dirección es obligatoria.',
            'city.required'          => 'La ciudad es obligatoria.',
            'country_code.required'  => 'El país es obligatorio.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first());
        }

        $gateway = PaymentGateway::forTenant($tenant->id)->where('is_active', true)->find($data['payment_gateway_id']);
        if (!$gateway) {
            return $this->errorResponse('El método de pago elegido ya no está disponible.');
        }

        if ($cart->hasStockIssues()) {
            return $this->errorResponse('Algunos productos de tu carrito ya no tienen stock suficiente. Vuelve al carrito para ajustarlos.');
        }

        try {
            // Un solo camino de creación de pedidos (web, API y chat): ver OrderService.
            $order = (new \Aero\Shop\Classes\OrderService())->create(
                $tenant->id,
                array_map(fn ($l) => ['product_id' => $l['product']->id, 'variant_id' => $l['variant']?->id, 'quantity' => $l['quantity']], $lines),
                [
                    'user_id'    => $this->currentUserId(),
                    'first_name' => $data['first_name'],
                    'last_name'  => $data['last_name'] ?? null,
                    'email'      => $data['email'],
                    'phone'      => $data['phone'],
                ],
                $gateway->id,
                [
                    'shipping' => $requiresShipping ? [
                        'address_line1'  => $data['address_line1'],
                        'address_line2'  => $data['address_line2'] ?? null,
                        'city'           => $data['city'],
                        'state_province' => $data['state_province'] ?? null,
                        'postal_code'    => $data['postal_code'] ?? null,
                        'country_code'   => $data['country_code'],
                    ] : null,
                    'customer_notes' => $data['customer_notes'] ?? null,
                    'source'         => 'web',
                ]
            );
        } catch (InsufficientStockException $e) {
            return $this->errorResponse($e->getMessage() . ' Vuelve al carrito para ajustar la cantidad.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $cart->clear();

        return Redirect::to('/tienda/pedido/' . $order->access_token);
    }

    protected function placeWhatsappOrder($tenant, $settings, CartService $cart, array $lines)
    {
        $data = post();
        $phone = \Aero\Shop\Classes\WhatsappCheckout::normalizePhone($data['phone'] ?? null);
        if (!$phone) {
            return $this->errorResponse('Ingresa un celular válido (con prefijo de país, ej. 59171234567).');
        }

        $isApi = $settings->whatsapp_mode !== 'market';
        if ($isApi && !$settings->whatsapp_number) {
            return $this->errorResponse('La tienda aún no tiene un número de WhatsApp configurado.');
        }

        $requiresShipping = $cart->requiresShipping();
        $address = trim((string) ($data['address_line1'] ?? ''));
        if ($requiresShipping && $address === '') {
            return $this->errorResponse('Indica la dirección o referencia de entrega.');
        }

        if ($cart->hasStockIssues()) {
            return $this->errorResponse('Algunos productos de tu carrito ya no tienen stock suficiente. Vuelve al carrito para ajustarlos.');
        }

        try {
            $order = (new \Aero\Shop\Classes\OrderService())->create(
                $tenant->id,
                array_map(fn ($l) => ['product_id' => $l['product']->id, 'variant_id' => $l['variant']?->id, 'quantity' => $l['quantity']], $lines),
                ['user_id' => $this->currentUserId(), 'phone' => $phone],
                null,
                [
                    'shipping' => $requiresShipping ? [
                        'address_line1' => mb_substr($address, 0, 200), 'city' => 'Por coordinar', 'country_code' => 'BO',
                    ] : null,
                    'customer_notes' => mb_substr((string) ($data['customer_notes'] ?? ''), 0, 1000) ?: null,
                    'source'         => 'whatsapp',
                ]
            );
        } catch (InsufficientStockException $e) {
            return $this->errorResponse($e->getMessage() . ' Vuelve al carrito para ajustar la cantidad.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $cart->clear();

        $text = \Aero\Shop\Classes\WhatsappCheckout::buildMessage($order) . "\n\n" . \Aero\Shop\Classes\OrderService::publicUrl($order);
        if ($isApi) {
            return Redirect::to(\Aero\Shop\Classes\WhatsappCheckout::apiUrl($settings->whatsapp_number, $text));
        }

        \Aero\Shop\Classes\WhatsappCheckout::sendViaHello($settings, $order, $phone);

        return Redirect::to('/tienda/pedido/' . $order->access_token);
    }

    protected function currentUserId(): ?int
    {
        if (!class_exists(\RainLab\User\Models\User::class)) {
            return null;
        }
        return Auth::user()?->id;
    }

    protected function errorResponse(string $message): array
    {
        return [
            '#checkout-error' => '<p class="text-sm text-red-500 mb-4">' . e($message) . '</p>',
        ];
    }
}
