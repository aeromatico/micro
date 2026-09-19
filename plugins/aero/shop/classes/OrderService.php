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
     * @param array $options  ['shipping'=>[address_line1,city,country_code,...], 'customer_notes'=>, 'notes'=>]
     *
     * @throws OrderException|InsufficientStockException
     */
    public function create(int $tenantId, array $items, array $customer, ?int $gatewayId = null, array $options = []): Order
    {
        $settings = ShopSettings::where('tenant_id', $tenantId)->first();
        if (!$settings || !$settings->is_enabled || !$settings->base_currency_id) {
            throw new OrderException('La tienda no está disponible en este momento.');
        }

        $lines = $this->resolveLines($tenantId, $items);
        $requiresShipping = collect($lines)->contains(fn ($l) => $l['product']->requires_shipping);

        $gateway = null;
        if ($gatewayId) {
            $gateway = PaymentGateway::forTenant($tenantId)->where('is_active', true)->find($gatewayId);
            if (!$gateway) {
                throw new OrderException('El método de pago elegido no está disponible.');
            }
        }

        $shipping = $options['shipping'] ?? null;
        if ($requiresShipping && (empty($shipping['address_line1']) || empty($shipping['city']))) {
            throw new OrderException('Estos productos requieren envío: falta la dirección y la ciudad.');
        }

        if (empty($customer['email']) && empty($customer['phone'])) {
            throw new OrderException('Falta el correo o el teléfono del cliente.');
        }

        $order = Db::transaction(function () use ($tenantId, $settings, $lines, $customer, $gateway, $requiresShipping, $shipping, $options) {
            $customerModel = $this->resolveCustomer($tenantId, $customer);

            $addressId = null;
            if ($requiresShipping) {
                $addressId = Address::create([
                    'tenant_id' => $tenantId, 'customer_id' => $customerModel->id, 'type' => 'shipping',
                    'full_name' => $customerModel->full_name, 'phone' => $customerModel->phone,
                    'address_line1' => $shipping['address_line1'], 'address_line2' => $shipping['address_line2'] ?? null,
                    'city' => $shipping['city'], 'state_province' => $shipping['state_province'] ?? null,
                    'postal_code' => $shipping['postal_code'] ?? null, 'country_code' => strtoupper($shipping['country_code'] ?? 'BO'),
                ])->id;
            }

            $subtotal = round(array_sum(array_column($lines, 'line_total')), 4);
            $status = $gateway ? 'awaiting_payment' : 'pending';

            $order = Order::create([
                'tenant_id' => $tenantId, 'customer_id' => $customerModel->id,
                'order_number' => (new OrderNumberGenerator())->generate($tenantId), 'status' => $status,
                'currency_id' => $settings->base_currency_id, 'exchange_rate_snapshot' => 1,
                'subtotal' => $subtotal, 'grand_total' => $subtotal, 'payment_gateway_id' => $gateway?->id,
                'shipping_address_id' => $addressId, 'billing_address_id' => $addressId,
                'customer_notes' => $options['customer_notes'] ?? null, 'notes' => $options['notes'] ?? null,
                'requires_shipping' => $requiresShipping,
            ]);

            foreach ($lines as $line) {
                OrderItem::create([
                    'tenant_id' => $tenantId, 'order_id' => $order->id, 'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']?->id, 'product_name_snapshot' => $line['product']->name,
                    'variant_label_snapshot' => $line['label'], 'sku_snapshot' => $line['sku'], 'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'], 'line_total' => $line['line_total'], 'product_type_snapshot' => $line['product']->type,
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

        return $order->fresh(['items', 'customer', 'currency', 'payment_gateway']);
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

        return $order->fresh();
    }

    /** URL pública del pedido en la tienda del tenant (con el token, sin sesión). */
    public static function publicUrl(Order $order): string
    {
        $tenant = $order->tenant ?: \Aero\Sites\Models\Tenant::find($order->tenant_id);

        return 'https://' . $tenant->primary_domain . '/tienda/pedido/' . $order->access_token;
    }

    // ------------------------------------------------------------------

    protected function resolveLines(int $tenantId, array $items): array
    {
        // Mismo producto+variante repetido = una línea.
        $merged = [];
        foreach ($items as $item) {
            $key = (int) ($item['product_id'] ?? 0) . '-' . (int) ($item['variant_id'] ?? 0);
            $merged[$key] = ($merged[$key] ?? 0) + (int) ($item['quantity'] ?? 1);
        }

        if (!$merged) {
            throw new OrderException('El pedido no tiene productos.');
        }

        $inventory = new InventoryService();
        $lines = [];

        foreach ($merged as $key => $qty) {
            [$productId, $variantId] = array_map('intval', explode('-', $key));

            if ($qty < 1 || $qty > 999) {
                throw new OrderException('Cantidad no válida.');
            }

            $product = Product::forTenant($tenantId)->active()->find($productId);
            if (!$product) {
                throw new OrderException('Un producto ya no está disponible.');
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

            $price = $variant ? (float) $variant->price : (float) $product->base_price;
            $lines[] = [
                'product' => $product, 'variant' => $variant, 'quantity' => $qty, 'unit_price' => $price,
                'line_total' => round($price * $qty, 4), 'label' => $variant?->label, 'sku' => $variant?->sku ?: $product->sku,
            ];
        }

        return $lines;
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
