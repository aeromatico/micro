<?php namespace Aero\Shop\Classes;

use Aero\Shop\Classes\Exceptions\InsufficientStockException;
use Aero\Shop\Classes\Exceptions\OrderException;
use Aero\Shop\Models\Address;
use Aero\Shop\Models\Customer;
use Aero\Shop\Models\Order;
use Aero\Shop\Models\OrderItem;
use Aero\Shop\Models\PaymentGateway;
use Aero\Shop\Models\Product;
use Aero\Shop\Models\ProductVariant;
use Aero\Shop\Models\ShopSettings;
use Db;

/**
 * Crea y cancela pedidos reales de la tienda de un tenant. Es el único
 * camino de creación: lo usan el checkout web, la API REST y el chat, para que
 * precios, stock, numeración y cobro se comporten igual en todos.
 *
 * Los precios salen SIEMPRE del catálogo (nunca del llamador) y se congelan en
 * OrderItem, igual que el checkout web.
 */
class OrderService
{
    /**
     * @param array $items    [['product_id'=>, 'variant_id'=>?, 'quantity'=>]]
     * @param array $customer ['first_name','last_name'?,'email'?,'phone'?,'user_id'?] — email o phone obligatorio
     * @param array $options  ['shipping'=>[address_line1,city,country_code,...], 'customer_notes'=>, 'notes'=>,
     *                         'source'=>web|pos|api|..., 'cashier_id'=>, 'walk_in'=>true (venta sin datos de cliente),
     *                         'allow_internal'=>true (permite «Venta libre» con precio propio), 'tax_id'=>, 'tax_name'=>]
     *
     * @throws OrderException|InsufficientStockException
     */
    public function create(int $tenantId, array $items, array $customer, ?int $gatewayId = null, array $options = []): Order
    {
        $settings = ShopSettings::where('tenant_id', $tenantId)->first();
        if (!$settings || !$settings->is_enabled || !$settings->base_currency_id) {
            throw new OrderException('La tienda no está disponible en este momento.');
        }

        if (!$settings->isRestaurantStore() && !$settings->isOpenNow()) {
            throw new OrderException('La tienda está cerrada en este momento.');
        }

        $lines = $this->resolveLines($tenantId, $items, !empty($options['allow_internal']));
        $requiresShipping = collect($lines)->contains(fn ($l) => $l['product']->requires_shipping);

        $restaurant = $settings->isRestaurantStore() ? $this->resolveRestaurant($settings, $lines, $options) : null;
        // Sucursal elegida en el checkout (el checkout la valida; la API/POS pueden no enviarla).
        $branch = $settings->findActiveBranch($options['branch_id'] ?? null);
        if ($restaurant) {
            // En restaurante solo el delivery lleva dirección, sin importar el flag del producto.
            $requiresShipping = $restaurant['order_type'] === 'delivery';
        }

        $gateway = null;
        if ($gatewayId) {
            $gateway = PaymentGateway::forTenant($tenantId)->where('is_active', true)->find($gatewayId);
            if (!$gateway) {
                throw new OrderException('El método de pago elegido no está disponible.');
            }
        }

        $shipping = $options['shipping'] ?? null;
        // Con coordenadas (ubicación compartida) la dirección y la ciudad son opcionales.
        $hasCoordinates = isset($shipping['latitude'], $shipping['longitude']);
        if ($requiresShipping && !$hasCoordinates && (empty($shipping['address_line1']) || empty($shipping['city']))) {
            throw new OrderException('Estos productos requieren envío: falta la dirección y la ciudad.');
        }

        if (empty($customer['email']) && empty($customer['phone']) && empty($options['walk_in'])) {
            throw new OrderException('Falta el correo o el teléfono del cliente.');
        }

        $order = Db::transaction(function () use ($tenantId, $settings, $lines, $customer, $gateway, $requiresShipping, $shipping, $options, $restaurant) {
            $customerModel = empty($customer['email']) && empty($customer['phone'])
                ? $this->walkInCustomer($tenantId)
                : $this->resolveCustomer($tenantId, $customer);

            // NIT / razón social capturados en la venta (para emitir factura por fuera).
            if (!empty($options['tax_id']) || !empty($options['tax_name'])) {
                $customerModel->fill(array_filter(['tax_id' => $options['tax_id'] ?? null, 'tax_name' => $options['tax_name'] ?? null]))->save();
            }

            $addressId = null;
            if ($requiresShipping) {
                $addressId = Address::create([
                    'tenant_id' => $tenantId, 'customer_id' => $customerModel->id, 'type' => 'shipping',
                    'full_name' => $customerModel->full_name, 'phone' => $customerModel->phone,
                    'address_line1' => $shipping['address_line1'] ?? null,
                    // Con coordenadas, el enlace al mapa queda a la vista dondequiera que se muestre la dirección.
                    'address_line2' => $shipping['address_line2']
                        ?? (isset($shipping['latitude'], $shipping['longitude'])
                            ? "📍 https://www.google.com/maps?q={$shipping['latitude']},{$shipping['longitude']}" : null),
                    'latitude' => $shipping['latitude'] ?? null, 'longitude' => $shipping['longitude'] ?? null,
                    'location_label' => $shipping['location_label'] ?? null,
                    'city' => $shipping['city'] ?? null, 'state_province' => $shipping['state_province'] ?? null,
                    'postal_code' => $shipping['postal_code'] ?? null, 'country_code' => strtoupper($shipping['country_code'] ?? 'BO'),
                ])->id;
            }

            $subtotal = round(array_sum(array_column($lines, 'line_total')), 4);
            $status = $gateway ? 'awaiting_payment' : 'pending';
            $deliveryFee = $restaurant['delivery_fee'] ?? 0;

            $order = Order::create([
                'tenant_id' => $tenantId, 'customer_id' => $customerModel->id,
                'order_number' => (new OrderNumberGenerator())->generate($tenantId), 'status' => $status,
                'currency_id' => $settings->base_currency_id, 'exchange_rate_snapshot' => 1,
                'subtotal' => $subtotal, 'shipping_total' => $deliveryFee, 'grand_total' => round($subtotal + $deliveryFee, 4), 'payment_gateway_id' => $gateway?->id,
                'shipping_address_id' => $addressId, 'billing_address_id' => $addressId,
                'customer_notes' => $options['customer_notes'] ?? null, 'notes' => $options['notes'] ?? null,
                'requires_shipping' => $requiresShipping,
                'order_type' => $restaurant['order_type'] ?? null, 'table_label' => $restaurant['table_label'] ?? null,
                'scheduled_for' => $restaurant['scheduled_for'] ?? null,
                'kitchen_status' => $restaurant ? 'new' : null, 'kitchen_updated_at' => $restaurant ? now() : null,
                'source' => $options['source'] ?? 'web', 'cashier_backend_user_id' => $options['cashier_id'] ?? null,
                'branch_id' => $branch['id'] ?? null, 'branch_name' => $branch['name'] ?? null,
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'tenant_id' => $tenantId, 'order_id' => $order->id, 'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']?->id, 'product_name_snapshot' => $line['product']->name,
                    'variant_label_snapshot' => $line['label'], 'sku_snapshot' => $line['sku'], 'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'], 'line_total' => $line['line_total'], 'product_type_snapshot' => $line['product']->type,
                    'modifiers' => $line['modifiers'] ?: null, 'note' => $line['note'],
                    'round' => 1, 'fired_at' => $restaurant ? now() : null,
                ]);
            }

            $order->load('items');
            (new InventoryService())->reserveForOrderStrict($order);

            $order->status_history()->create(['from_status' => null, 'to_status' => $status, 'note' => $options['source'] ?? null]);

            if ($gateway && $gateway->driver === 'pagos_qr') {
                \Aero\Shop\Classes\PaymentGateways\PagosQrGateway::issueForOrder($gateway, $order);
            }

            return $order;
        });

        $order = $order->fresh(['items', 'customer', 'currency', 'payment_gateway']);
        OrderNotifier::fire($order, 'placed');

        return $order;
    }

    /** Cancela un pedido sin pagar y devuelve el stock reservado. */
    public function cancel(Order $order, ?string $reason = null): Order
    {
        if (!in_array($order->status, ['pending', 'awaiting_payment'], true)) {
            throw new OrderException('Solo se pueden cancelar pedidos pendientes de pago.');
        }

        Db::transaction(function () use ($order, $reason) {
            $from = $order->status;
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancel_reason = $reason;
            $order->save();

            $order->status_history()->create(['from_status' => $from, 'to_status' => 'cancelled', 'note' => $reason]);
            (new InventoryService())->releaseForOrder($order->load('items'));
        });

        $order = $order->fresh();
        OrderNotifier::fire($order, 'cancelled');

        return $order;
    }

    // ------------------------------------------------------------------
    // Venta presencial (aero/pos): cuentas abiertas, cobro, descuento, anulación
    // ------------------------------------------------------------------

    /** Suma los renglones y recalcula el total: subtotal − descuento + envío + impuestos. */
    public function recalculateTotals(Order $order): Order
    {
        $subtotal = round((float) $order->items()->sum('line_total'), 4);
        $order->subtotal = $subtotal;
        $order->grand_total = round(max(0, $subtotal - (float) $order->discount_total) + (float) $order->shipping_total + (float) $order->tax_total, 4);
        $order->save();

        return $order;
    }

    /**
     * Agrega una ronda de platos a una cuenta abierta (aún sin cobrar). Reserva solo el
     * stock de lo nuevo y, si cocina ya había terminado, reabre el pedido como «nuevo».
     *
     * @throws OrderException|InsufficientStockException
     */
    public function appendItems(Order $order, array $items, array $options = []): Order
    {
        if ($order->paid_at || !in_array($order->status, ['pending', 'awaiting_payment', 'fulfilled'], true)) {
            throw new OrderException('Esta cuenta ya está cerrada: no se pueden agregar platos.');
        }

        $lines = $this->resolveLines((int) $order->tenant_id, $items, !empty($options['allow_internal']));

        Db::transaction(function () use ($order, $lines, $options) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $round = (int) $locked->items()->max('round') + 1;
            $restaurant = $locked->kitchen_status !== null;

            $created = [];
            foreach ($lines as $line) {
                $created[] = OrderItem::create([
                    'tenant_id' => $locked->tenant_id, 'order_id' => $locked->id, 'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']?->id, 'product_name_snapshot' => $line['product']->name,
                    'variant_label_snapshot' => $line['label'], 'sku_snapshot' => $line['sku'], 'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'], 'line_total' => $line['line_total'], 'product_type_snapshot' => $line['product']->type,
                    'modifiers' => $line['modifiers'] ?: null, 'note' => $line['note'],
                    'round' => $round, 'fired_at' => $restaurant ? now() : null,
                ]);
            }

            // Solo el stock de la ronda nueva.
            $locked->setRelation('items', collect($created));
            (new InventoryService())->reserveForOrderStrict($locked);

            $from = $locked->status;
            if ($from === 'fulfilled') {
                $locked->status = 'pending';
            }
            if ($restaurant && in_array($locked->kitchen_status, ['ready', 'delivered'], true)) {
                $locked->kitchen_status = 'new';
                $locked->accepted_at = null;
                $locked->promised_at = null;
            }
            if ($restaurant) {
                $locked->kitchen_updated_at = now();
            }
            $locked->save();

            $this->recalculateTotals($locked->fresh());
            $locked->status_history()->create([
                'from_status' => $from, 'to_status' => $locked->status, 'note' => 'ronda ' . $round,
                'changed_by_backend_user_id' => $options['cashier_id'] ?? null,
            ]);
        });

        return $order->fresh(['items', 'customer', 'currency']);
    }

    /** Descuento en monto sobre una cuenta sin cobrar. */
    public function applyDiscount(Order $order, float $amount, ?string $reason = null, ?int $userId = null): Order
    {
        if ($order->paid_at || in_array($order->status, ['cancelled', 'refunded'], true)) {
            throw new OrderException('No se puede dar descuento a una cuenta cobrada o anulada.');
        }
        $amount = round($amount, 4);
        if ($amount < 0 || $amount > (float) $order->subtotal) {
            throw new OrderException('El descuento no puede ser negativo ni mayor al subtotal.');
        }

        Db::transaction(function () use ($order, $amount, $reason, $userId) {
            $order->discount_total = $amount;
            $this->recalculateTotals($order);
            $order->status_history()->create([
                'from_status' => $order->status, 'to_status' => $order->status,
                'note' => 'descuento ' . number_format($amount, 2) . ($reason ? ': ' . $reason : ''),
                'changed_by_backend_user_id' => $userId,
            ]);
        });

        return $order->fresh();
    }

    /**
     * Marca el pedido como cobrado. Idempotente (si ya estaba pagado no hace nada). Un pedido
     * que cocina ya entregó conserva su estado «fulfilled» y solo registra el cobro.
     */
    public function markPaid(Order $order, ?int $gatewayId = null, ?string $reference = null, ?int $userId = null, bool $notify = true): Order
    {
        if ($order->paid_at) {
            return $order;
        }
        if (!in_array($order->status, ['pending', 'awaiting_payment', 'fulfilled'], true)) {
            throw new OrderException('Este pedido no se puede cobrar en su estado actual.');
        }

        Db::transaction(function () use ($order, $gatewayId, $reference, $userId) {
            $from = $order->status;
            $order->status = $from === 'fulfilled' ? 'fulfilled' : 'paid';
            $order->paid_at = now();
            $order->paid_confirmed_by_backend_user_id = $userId;
            if ($gatewayId) {
                $order->payment_gateway_id = $gatewayId;
            }
            if ($reference) {
                $order->payment_reference = $reference;
            }
            $order->save();

            $order->status_history()->create([
                'from_status' => $from, 'to_status' => $order->status, 'changed_by_backend_user_id' => $userId,
            ]);
        });

        $order = $order->fresh();
        if ($notify) {
            OrderNotifier::fire($order, 'paid');
        }

        return $order;
    }

    /**
     * Anula una venta en cualquier estado abierto o cobrado y devuelve el stock.
     * Sin cobrar queda «cancelled»; cobrada queda «refunded».
     */
    public function void(Order $order, string $reason, ?int $userId = null): Order
    {
        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            throw new OrderException('Esta venta ya está anulada.');
        }

        Db::transaction(function () use ($order, $reason, $userId) {
            $from = $order->status;
            $order->status = $order->paid_at ? 'refunded' : 'cancelled';
            $order->cancelled_at = now();
            $order->cancel_reason = $reason;
            $order->save();

            $order->status_history()->create([
                'from_status' => $from, 'to_status' => $order->status, 'note' => $reason, 'changed_by_backend_user_id' => $userId,
            ]);
            (new InventoryService())->releaseForOrder($order->load('items'), $userId);
        });

        $order = $order->fresh();
        OrderNotifier::fire($order, 'cancelled');

        return $order;
    }

    /** Cliente genérico para ventas de mostrador sin datos. */
    public function walkInCustomer(int $tenantId): Customer
    {
        return Customer::firstOrCreate(
            ['tenant_id' => $tenantId, 'email' => 'mostrador@sin-correo.invalid'],
            ['first_name' => 'Cliente', 'last_name' => 'Mostrador', 'is_guest' => true]
        );
    }

    /** Producto interno «Venta libre» (monto abierto), oculto de la tienda pública. */
    public function freeSaleProduct(int $tenantId): Product
    {
        return Product::withTrashed()->firstOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'venta-libre'],
            ['name' => 'Venta libre', 'type' => 'physical', 'status' => 'active', 'is_internal' => true,
             'base_price' => 0, 'track_inventory' => false, 'requires_shipping' => false, 'has_variants' => false]
        );
    }

    /** URL pública del pedido en la tienda del tenant (con el token, sin sesión). */
    public static function publicUrl(Order $order): string
    {
        $tenant = $order->tenant ?: \Aero\Sites\Models\Tenant::find($order->tenant_id);

        return 'https://' . $tenant->primary_domain . '/tienda/pedido/' . $order->access_token;
    }

    // ------------------------------------------------------------------

    protected function resolveLines(int $tenantId, array $items, bool $allowInternal = false): array
    {
        // Mismo producto+variante repetido = una línea.
        $merged = [];
        $extras = [];
        foreach ($items as $item) {
            $uids = array_values(array_filter(array_map('strval', (array) ($item['modifiers'] ?? []))));
            $note = RestaurantService::cleanNote($item['note'] ?? null);
            $suffix = RestaurantService::suffix($uids, $note);
            $key = (int) ($item['product_id'] ?? 0) . '-' . (int) ($item['variant_id'] ?? 0) . ($suffix ? '-' . $suffix : '');
            // Monto abierto: solo para productos internos (Venta libre) y con permiso explícito.
            $customPrice = $allowInternal && isset($item['price']) && is_numeric($item['price']) ? round(max(0, (float) $item['price']), 4) : null;
            if ($customPrice !== null) {
                $key .= '-p' . $customPrice;
            }
            $extras[$key] = ['mods' => $uids, 'note' => $note, 'price' => $customPrice];
            $merged[$key] = ($merged[$key] ?? 0) + (int) ($item['quantity'] ?? 1);
        }

        if (!$merged) {
            throw new OrderException('El pedido no tiene productos.');
        }

        $inventory = new InventoryService();
        $lines = [];

        foreach ($merged as $key => $qty) {
            [$productId, $variantId] = array_map('intval', array_pad(explode('-', $key, 3), 2, 0));

            if ($qty < 1 || $qty > 999) {
                throw new OrderException('Cantidad no válida.');
            }

            $product = Product::forTenant($tenantId)->where('status', 'active')
                ->when(!$allowInternal, fn ($q) => $q->where('is_internal', false))->find($productId);
            if (!$product) {
                throw new OrderException('Un producto ya no está disponible.');
            }
            if ($qty < (int) $product->min_quantity) {
                throw new OrderException('"' . $product->name . '" se pide desde ' . $product->min_quantity . ' unidades.');
            }

            $variant = null;
            if ($product->has_variants) {
                if (!$variantId) {
                    throw new OrderException('"' . $product->name . '" tiene variantes: elige una.');
                }
                $variant = ProductVariant::forTenant($tenantId)->where('product_id', $product->id)->where('is_active', true)->with('option_values')->find($variantId);
                if (!$variant) {
                    throw new OrderException('La variante elegida de "' . $product->name . '" ya no está disponible.');
                }
            }

            if (!$inventory->checkAvailability($product, $variant, $qty)) {
                throw new InsufficientStockException('Stock insuficiente para "' . $product->name . '".');
            }

            $price = $extras[$key]['price'] ?? ($variant ? (float) $variant->price : (float) $product->base_price);

            $modifiers = [];
            if ($extras[$key]['mods'] ?? null) {
                $resolved = RestaurantService::resolve($product, $extras[$key]['mods']);
                $modifiers = $resolved['snapshot'];
                $price += $resolved['delta'];
            } else {
                RestaurantService::resolve($product, []); // exige los grupos con mínimo > 0
            }

            $lines[] = [
                'modifiers' => $modifiers, 'note' => $extras[$key]['note'] ?? null,
                'product' => $product, 'variant' => $variant, 'quantity' => $qty, 'unit_price' => $price,
                'line_total' => round($price * $qty, 4), 'label' => $variant?->label, 'sku' => $variant?->sku ?: $product->sku,
            ];
        }

        return $lines;
    }

    /**
     * Reglas del restaurante: tipo de pedido habilitado, horario, mínimo y
     * costo de delivery. Devuelve los datos a guardar en el pedido.
     */
    protected function resolveRestaurant(ShopSettings $settings, array $lines, array $options): array
    {
        $cfg = $settings->restaurant();
        $types = $settings->enabledOrderTypes();
        $type = $options['order_type'] ?? array_key_first($types);

        if (!isset($types[$type])) {
            throw new OrderException('Ese tipo de pedido no está disponible.');
        }

        $lead = max(0, (int) ($cfg['lead_minutes'] ?? 30));
        $scheduled = null;
        if (!$settings->isOpenNow()) {
            if (empty($cfg['accept_closed'])) {
                throw new OrderException('El restaurante está cerrado en este momento.');
            }
            $scheduled = !empty($options['scheduled_for']) ? \Carbon\Carbon::parse($options['scheduled_for']) : null;
            if (!$scheduled || $scheduled->lt(now()->addMinutes($lead)) || !$settings->isOpenNow($scheduled)) {
                throw new OrderException('Estamos cerrados: elige una hora de entrega dentro de nuestro horario (con al menos '.$lead.' min de anticipación).');
            }
        } elseif (!empty($options['scheduled_for'])) {
            $at = \Carbon\Carbon::parse($options['scheduled_for']);
            if ($at->gte(now()->addMinutes($lead)) && $settings->isOpenNow($at)) {
                $scheduled = $at;
            }
        }

        $table = null;
        if ($type === 'dine_in') {
            $table = mb_substr(trim((string) ($options['table_label'] ?? '')), 0, 30) ?: null;
        }

        return [
            'order_type' => $type, 'table_label' => $table, 'scheduled_for' => $scheduled,
            'delivery_fee' => $type === 'delivery' ? (float) $cfg['delivery_fee'] : 0.0,
        ];
    }

    /** Un cliente por correo; si solo hay teléfono, por teléfono (con correo interno inválido a propósito). */
    protected function resolveCustomer(int $tenantId, array $c): Customer
    {
        $email = !empty($c['email']) ? strtolower(trim($c['email'])) : null;
        $phone = !empty($c['phone']) ? trim($c['phone']) : null;

        $found = $email
            ? Customer::forTenant($tenantId)->where('email', $email)->first()
            : Customer::forTenant($tenantId)->where('phone', $phone)->first();

        $attrs = array_filter([
            'first_name' => $c['first_name'] ?? null, 'last_name' => $c['last_name'] ?? null,
            'phone' => $phone, 'user_id' => $c['user_id'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        if ($found) {
            // No se pisa un nombre real con uno vacío.
            $found->fill($attrs)->save();

            return $found;
        }

        return Customer::create($attrs + [
            'tenant_id' => $tenantId,
            'email'     => $email ?: 'tel' . preg_replace('/\D+/', '', (string) $phone) . '@sin-correo.invalid',
            'first_name' => $c['first_name'] ?? 'Cliente',
        ]);
    }
}
