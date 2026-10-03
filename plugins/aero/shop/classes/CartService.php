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

    /** @return array{key: string, previous: int} línea afectada y cantidad que tenía antes (0 = nueva) */
    public function add(int $productId, ?int $variantId, int $qty, array $modifierUids = [], ?string $note = null): array
    {
        $note = RestaurantService::cleanNote($note);
        $suffix = RestaurantService::suffix($modifierUids, $note);
        $key = self::lineKey($productId, $variantId) . ($suffix ? '-' . $suffix : '');
        $items = $this->raw();
        $previous = (int) ($items[$key] ?? 0);
        $items[$key] = max(1, $previous + $qty);
        $this->persist($items);

        if ($suffix) {
            $meta = $this->meta();
            $meta[$key] = ['mods' => array_values($modifierUids), 'note' => $note];
            session([$this->sessionKey . '_meta' => $meta]);
        }

        return ['key' => $key, 'previous' => $previous];
    }

    /** Extras y nota por línea (solo líneas de restaurante). */
    protected function meta(): array
    {
        return session($this->sessionKey . '_meta', []);
    }

    public function setQuantity(string $key, int $qty): void
    {
        $items = $this->raw();
        if ($qty <= 0) {
            unset($items[$key]);
            $this->forgetMeta($key);
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
        $this->forgetMeta($key);
    }

    protected function forgetMeta(string $key): void
    {
        $meta = $this->meta();
        unset($meta[$key]);
        session([$this->sessionKey . '_meta' => $meta]);
    }

    public function clear(): void
    {
        session()->forget([$this->sessionKey, $this->sessionKey . '_meta']);
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
            [$productId, $variantId] = array_pad(explode('-', $key, 3), 2, 0);
            $variantId = (int) $variantId ?: null;
            $extras = $this->meta()[$key] ?? null;

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

            $modifiers = [];
            if ($extras) {
                try {
                    $resolved = RestaurantService::resolve($product, $extras['mods'] ?? []);
                } catch (\Aero\Shop\Classes\Exceptions\OrderException $e) {
                    continue; // un extra dejó de existir: la línea ya no es válida
                }
                $modifiers = $resolved['snapshot'];
                $price += $resolved['delta'];
            }
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
                'modifiers'     => $modifiers,
                'modifier_uids' => $extras['mods'] ?? [],
                'note'          => $extras['note'] ?? null,
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
