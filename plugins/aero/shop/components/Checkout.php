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
    public bool $restaurantStore = false;
    public array $orderTypes = [];
    public array $restaurant = [];
    /** Costo de envío de la tienda (0 si el carrito no lleva envío); el restaurante usa su delivery. */
    public float $deliveryFee = 0;
    public bool $isOpen = true;
    public ?string $table = null;
    public int $prepMinutes = 0;
    public string $pickerAssets = '';
    /** Sucursales activas para elegir en el checkout (vacío = no se manejan sucursales). */
    public array $branches = [];

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
        $settings = StorefrontContext::settings();
        $this->isOpen = $settings ? $settings->isOpenNow() : true;
        $this->branches = $settings?->activeBranches() ?? [];
        if ($settings?->isRestaurantStore()) {
            $this->restaurantStore = true;
            $this->restaurant = $settings->restaurant();
            $this->orderTypes = $settings->enabledOrderTypes();
            $this->table = session('aero_shop_table_' . $tenant->id);
            // Sin mesa por QR no se ofrece "Comer en local" con mesa fija, pero sigue disponible (el cliente la escribe).
        }
        // Mapa de ubicación exacta (aero/tracking). Sin el plugin, queda el campo de texto.
        if (($this->whatsappStore || $this->restaurantStore) && class_exists(\Aero\Tracking\Components\LocationPicker::class)) {
            $this->pickerAssets = \Aero\Tracking\Components\LocationPicker::assetTags();
        }

        $cart = new CartService($tenant->id);
        $this->lines = $cart->lines();
        $this->prepMinutes = (int) max(array_merge([0], array_map(fn ($l) => (int) $l['product']->prep_minutes, $this->lines)));
        $this->subtotal = $cart->subtotal();
        $this->requiresShipping = $cart->requiresShipping();
        $this->deliveryFee = $this->restaurantStore
            ? (float) ($this->restaurant['delivery_fee'] ?? 0)
            : ($this->requiresShipping ? (float) ($settings?->shipping_fee ?? 0) : 0.0);

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

        if (!$this->branchChosen($settings)) {
            return $this->errorResponse('Elige la sucursal para tu pedido.');
        }

        if ($settings->isRestaurantStore()) {
            return $this->placeRestaurantOrder($tenant, $settings, $cart, $lines);
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
                    'branch_id'      => $data['branch_id'] ?? null,
                ]
            );
        } catch (InsufficientStockException $e) {
            return $this->errorResponse($e->getMessage() . ' Vuelve al carrito para ajustar la cantidad.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $cart->clear();
        $this->rememberOrder($tenant->id, $order->access_token);

        return Redirect::to('/tienda/pedido/' . $order->access_token);
    }

    /** Sin sucursales activas no se pide nada; con ellas, la elegida debe existir y estar activa. */
    protected function branchChosen($settings): bool
    {
        return !$settings->activeBranches() || (bool) $settings->findActiveBranch(post('branch_id'));
    }

    protected function placeRestaurantOrder($tenant, $settings, CartService $cart, array $lines)
    {
        $data = post();
        $type = (string) ($data['order_type'] ?? '');

        $validator = Validator::make($data, [
            'first_name' => 'required|min:2|max:100',
            'phone'      => 'required|max:30',
            'payment_gateway_id' => 'nullable|exists:aero_shop_payment_gateways,id',
        ], [
            'first_name.required' => 'Dinos tu nombre para llamarte cuando esté listo.',
            'phone.required'      => 'El teléfono es obligatorio.',
        ]);
        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first());
        }

        $phone = \Aero\Shop\Classes\WhatsappCheckout::normalizePhone($data['phone']) ?: trim($data['phone']);

        $shipping = null;
        if ($type === 'delivery') {
            $address = trim((string) ($data['address_line1'] ?? ''));
            $lat = is_numeric($data['latitude'] ?? null) ? (float) $data['latitude'] : null;
            $lng = is_numeric($data['longitude'] ?? null) ? (float) $data['longitude'] : null;
            if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
                $lat = $lng = null;
            }
            if ($address === '' && $lat === null) {
                return $this->errorResponse('Marca tu ubicación en el mapa o escribe la dirección de entrega.');
            }
            $shipping = [
                'address_line1' => mb_substr($address, 0, 200) ?: 'Ubicación en el mapa', 'city' => 'Por coordinar', 'country_code' => 'BO',
                'latitude' => $lat, 'longitude' => $lng,
            ];
        }

        $gatewayId = null;
        if (!empty($data['payment_gateway_id'])) {
            $gatewayId = PaymentGateway::forTenant($tenant->id)->where('is_active', true)->whereKey($data['payment_gateway_id'])->value('id');
            if (!$gatewayId) {
                return $this->errorResponse('El método de pago elegido ya no está disponible.');
            }
        }

        $scheduled = null;
        if (!empty($data['scheduled_for'])) {
            try {
                $scheduled = \Carbon\Carbon::parse($data['scheduled_for']);
            } catch (\Throwable $e) {
                return $this->errorResponse('La hora elegida no es válida.');
            }
        }

        try {
            $order = (new \Aero\Shop\Classes\OrderService())->create(
                $tenant->id,
                array_map(fn ($l) => [
                    'product_id' => $l['product']->id, 'variant_id' => $l['variant']?->id, 'quantity' => $l['quantity'],
                    'modifiers' => $l['modifier_uids'], 'note' => $l['note'],
                ], $lines),
                [
                    'user_id' => $this->currentUserId(), 'first_name' => trim($data['first_name']), 'phone' => $phone,
                    'email' => filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: null,
                ],
                $gatewayId,
                [
                    'order_type' => $type, 'table_label' => $data['table_label'] ?? null, 'scheduled_for' => $scheduled,
                    'shipping' => $shipping,
                    'customer_notes' => mb_substr((string) ($data['customer_notes'] ?? ''), 0, 1000) ?: null,
                    'source' => 'web',
                    'branch_id' => $data['branch_id'] ?? null,
                ]
            );
        } catch (InsufficientStockException $e) {
            return $this->errorResponse($e->getMessage() . ' Vuelve al carrito para ajustar la cantidad.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $cart->clear();
        $this->rememberOrder($tenant->id, $order->access_token);

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
        $lat = is_numeric($data['latitude'] ?? null) ? (float) $data['latitude'] : null;
        $lng = is_numeric($data['longitude'] ?? null) ? (float) $data['longitude'] : null;
        if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
            $lat = $lng = null;
        }
        if ($requiresShipping && $address === '' && $lat === null) {
            return $this->errorResponse('Marca tu ubicación en el mapa o escribe la dirección de entrega.');
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
                        'address_line1' => mb_substr($address, 0, 200) ?: 'Ubicación en el mapa', 'city' => 'Por coordinar', 'country_code' => 'BO',
                        'latitude' => $lat, 'longitude' => $lng,
                    ] : null,
                    'customer_notes' => mb_substr((string) ($data['customer_notes'] ?? ''), 0, 1000) ?: null,
                    'source'         => 'whatsapp',
                    'branch_id'      => $data['branch_id'] ?? null,
                ]
            );
        } catch (InsufficientStockException $e) {
            return $this->errorResponse($e->getMessage() . ' Vuelve al carrito para ajustar la cantidad.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage());
        }

        $cart->clear();
        $this->rememberOrder($tenant->id, $order->access_token);

        $text = \Aero\Shop\Classes\WhatsappCheckout::buildMessage($order) . "\n\n" . \Aero\Shop\Classes\OrderService::publicUrl($order);
        if ($isApi) {
            return Redirect::to(\Aero\Shop\Classes\WhatsappCheckout::apiUrl($settings->whatsapp_number, $text));
        }

        \Aero\Shop\Classes\WhatsappCheckout::sendViaHello($settings, $order, $phone);

        return Redirect::to('/tienda/pedido/' . $order->access_token);
    }

    /** Guarda en la sesión los últimos pedidos para poder volver a su seguimiento desde la tienda. */
    protected function rememberOrder(int $tenantId, string $token): void
    {
        $key = 'aero_shop_orders_' . $tenantId;
        $tokens = array_values(array_unique(array_merge([$token], (array) session($key, []))));
        session([$key => array_slice($tokens, 0, 5)]);
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
