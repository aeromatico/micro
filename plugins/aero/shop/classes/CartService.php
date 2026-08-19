<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\Product;
use Aero\Shop\Models\ProductVariant;
use Aero\Shop\Models\ShopSettings;

/**
 * Carrito en sesión de servidor (no localStorage) — sobrevive recargas,
 * es igual para invitados y usuarios logueados, y queda disponible para
 * el checkout sin sincronización adicional. Aislado por tenant (un mismo
 * navegador puede visitar varios micrositios en dominios distintos, pero
 * por las dudas se scopea explícitamente por tenant_id en la key).
 */
class CartService
{
    protected int $tenantId;
    protected string $sessionKey;

    public function __construct(int $tenantId)
    {
        $this->tenantId = $tenantId;
        $this->sessionKey = "aero_shop_cart_{$tenantId}";
    }

    protected function raw(): array
    {
        return session($this->sessionKey, []);
    }

    protected function persist(array $items): void
    {
        session([$this->sessionKey => $items]);
    }

    public static function lineKey(int $productId, ?int $variantId): string
    {
        return $productId . '-' . ($variantId ?? 0);
    }

    public function add(int $productId, ?int $variantId, int $qty): void
    {
        $key = self::lineKey($productId, $variantId);
        $items = $this->raw();
        $items[$key] = max(1, ($items[$key] ?? 0) + $qty);
        $this->persist($items);
    }

    public function setQuantity(string $key, int $qty): void
    {
        $items = $this->raw();
        if ($qty <= 0) {
            unset($items[$key]);
        } else {
            $items[$key] = $qty;
        }
        $this->persist($items);
    }

    public function remove(string $key): void
    {
        $items = $this->raw();
        unset($items[$key]);
        $this->persist($items);
    }

    public function clear(): void
    {
        session()->forget($this->sessionKey);
    }

    public function isEmpty(): bool
    {
        return empty($this->raw());
    }

    public function count(): int
    {
        return (int) array_sum($this->raw());
    }

    /**
     * Hidrata las líneas del carrito con datos EN VIVO de Product/ProductVariant
     * (precio y stock actuales, no snapshot) — el carrito siempre refleja el
     * catálogo real hasta que el checkout confirma el pedido y recién ahí
     * congela precios en OrderItem.
     */
    public function lines(): array
    {
        $items = $this->raw();
        if (!$items) {
            return [];
        }

        $inventory = new InventoryService();
        $lines = [];

        foreach ($items as $key => $qty)
        {
            [$productId, $variantId] = array_pad(explode('-', $key, 2), 2, 0);
            $variantId = (int) $variantId ?: null;

            $product = Product::forTenant($this->tenantId)->where('status', 'active')->find((int) $productId);
            if (!$product) {
                continue;
            }

            $variant = null;
            if ($variantId) {
                $variant = ProductVariant::forTenant($this->tenantId)
                    ->where('product_id', $product->id)
                    ->where('is_active', true)
                    ->with('option_values')
                    ->find($variantId);
                if (!$variant) {
                    continue;
                }
            }

            $price = $variant ? (float) $variant->price : (float) $product->base_price;
            $image = $variant?->image ?: $product->images->first();

            $lines[] = [
                'key'           => $key,
                'product'       => $product,
                'variant'       => $variant,
                'quantity'      => (int) $qty,
                'unit_price'    => $price,
                'line_total'    => round($price * (int) $qty, 4),
                'image'         => $image,
                'label'         => $variant?->label,
                'sku'           => $variant?->sku ?: $product->sku,
                'in_stock'      => $inventory->checkAvailability($product, $variant, (int) $qty),
                'max_available' => ShopSettings::inventoryEnabledForTenant($this->tenantId) && $product->track_inventory && !$product->allow_backorder
                    ? ($variant ? $variant->stock_quantity : $product->stock_quantity)
                    : null,
            ];
        }

        return $lines;
    }

    public function subtotal(): float
    {
        return round(array_sum(array_column($this->lines(), 'line_total')), 4);
    }

    public function requiresShipping(): bool
    {
        foreach ($this->lines() as $line) {
            if ($line['product']->requires_shipping) {
                return true;
            }
        }
        return false;
    }

    public function hasStockIssues(): bool
    {
        foreach ($this->lines() as $line) {
            if (!$line['in_stock']) {
                return true;
            }
        }
        return false;
    }
}
