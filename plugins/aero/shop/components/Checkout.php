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
            $order = Db::transaction(function () use ($tenant, $settings, $data, $lines, $gateway, $requiresShipping, $cart) {
                $userId = $this->currentUserId();

                $customer = Customer::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'email' => $data['email']],
                    [
                        'user_id'    => $userId,
                        'first_name' => $data['first_name'],
                        'last_name'  => $data['last_name'] ?? null,
                        'phone'      => $data['phone'],
                    ]
                );

                $shippingAddressId = null;
                if ($requiresShipping) {
                    $address = Address::create([
                        'tenant_id'      => $tenant->id,
                        'customer_id'    => $customer->id,
                        'type'           => 'shipping',
                        'full_name'      => trim($data['first_name'] . ' ' . ($data['last_name'] ?? '')),
                        'phone'          => $data['phone'],
                        'address_line1'  => $data['address_line1'],
                        'address_line2'  => $data['address_line2'] ?? null,
                        'city'           => $data['city'],
                        'state_province' => $data['state_province'] ?? null,
                        'postal_code'    => $data['postal_code'] ?? null,
                        'country_code'   => strtoupper($data['country_code']),
                    ]);
                    $shippingAddressId = $address->id;
                }

                $orderNumber = (new OrderNumberGenerator())->generate($tenant->id);
                $subtotal = $cart->subtotal();

                $order = Order::create([
                    'tenant_id'           => $tenant->id,
                    'customer_id'         => $customer->id,
                    'order_number'        => $orderNumber,
                    'status'              => 'awaiting_payment',
                    'currency_id'         => $settings->base_currency_id,
                    'exchange_rate_snapshot' => 1,
                    'subtotal'            => $subtotal,
                    'grand_total'         => $subtotal,
                    'payment_gateway_id'  => $gateway->id,
                    'shipping_address_id' => $shippingAddressId,
                    'billing_address_id'  => $shippingAddressId,
                    'customer_notes'      => $data['customer_notes'] ?? null,
                    'requires_shipping'   => $requiresShipping,
                ]);

                foreach ($lines as $line) {
                    OrderItem::create([
                        'tenant_id'              => $tenant->id,
                        'order_id'               => $order->id,
                        'product_id'             => $line['product']->id,
                        'product_variant_id'     => $line['variant']?->id,
                        'product_name_snapshot'  => $line['product']->name,
                        'variant_label_snapshot' => $line['label'],
                        'sku_snapshot'           => $line['sku'],
                        'unit_price'             => $line['unit_price'],
                        'quantity'               => $line['quantity'],
                        'line_total'             => $line['line_total'],
                        'product_type_snapshot'  => $line['product']->type,
                    ]);
                }

                $order->load('items');
                (new InventoryService())->reserveForOrderStrict($order);

                $order->status_history()->create([
                    'from_status' => null,
                    'to_status'   => 'awaiting_payment',
                ]);

                return $order;
            });
        } catch (InsufficientStockException $e) {
            return $this->errorResponse($e->getMessage() . ' Vuelve al carrito para ajustar la cantidad.');
        }

        $cart->clear();

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
