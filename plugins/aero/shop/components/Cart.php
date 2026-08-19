<?php namespace Aero\Shop\Components;

use Aero\Shop\Classes\CartService;
use Aero\Shop\Classes\InventoryService;
use Aero\Shop\Classes\StorefrontContext;
use Aero\Shop\Models\Product;
use Aero\Shop\Models\ProductVariant;
use Cms\Classes\ComponentBase;

class Cart extends ComponentBase
{
    public array $lines = [];
    public float $subtotal = 0;
    public int $count = 0;
    public bool $requiresShipping = false;
    public ?\Aero\Shop\Models\Currency $currency = null;

    protected ?CartService $cartService = null;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Shop Carrito',
            'description' => 'Carrito de compra en sesión: badge de cabecera + página completa del carrito.',
        ];
    }

    public function onRun(): void
    {
        $this->hydrate();
    }

    protected function hydrate(): void
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return;
        }

        $this->currency = StorefrontContext::currency();
        $service = $this->cart($tenant->id);

        $this->lines = $service->lines();
        $this->subtotal = $service->subtotal();
        $this->count = $service->count();
        $this->requiresShipping = $service->requiresShipping();
    }

    protected function cart(int $tenantId): CartService
    {
        return $this->cartService ??= new CartService($tenantId);
    }

    public function onAdd()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return $this->errorResponse('Tienda no encontrada.');
        }

        $productId = (int) post('product_id');
        $variantId = post('variant_id') ? (int) post('variant_id') : null;
        $qty = max(1, (int) post('quantity', 1));

        $product = Product::forTenant($tenant->id)->active()->find($productId);
        if (!$product) {
            return $this->errorResponse('Este producto ya no está disponible.');
        }

        $variant = null;
        if ($product->has_variants) {
            if (!$variantId) {
                return $this->errorResponse('Selecciona una opción antes de agregar al carrito.');
            }
            $variant = ProductVariant::forTenant($tenant->id)
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->find($variantId);
            if (!$variant) {
                return $this->errorResponse('Esa combinación ya no está disponible.');
            }
        }

        if (!(new InventoryService)->checkAvailability($product, $variant, $qty)) {
            return $this->errorResponse('No hay suficiente stock disponible.');
        }

        $this->cart($tenant->id)->add($productId, $variantId, $qty);
        $this->hydrate();

        return $this->renderUpdates();
    }

    public function onUpdateQuantity()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return [];
        }

        $key = (string) post('key');
        $qty = max(0, (int) post('quantity', 1));

        $this->cart($tenant->id)->setQuantity($key, $qty);
        $this->hydrate();

        return $this->renderUpdates();
    }

    public function onRemove()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return [];
        }

        $this->cart($tenant->id)->remove((string) post('key'));
        $this->hydrate();

        return $this->renderUpdates();
    }

    protected function renderUpdates(): array
    {
        return [
            '#cart-badge'   => $this->renderPartial('@badge'),
            '#cart-content' => $this->renderPartial('@default'),
        ];
    }

    protected function errorResponse(string $message): array
    {
        return [
            '#cart-error' => '<p class="text-sm text-red-500 mb-4">' . e($message) . '</p>',
        ];
    }

    /**
     * Llamado desde header.htm (presente en todas las páginas) para pintar
     * el ícono + contador del carrito dentro del <a id="cart-badge"> estático.
     */
    public function renderBadge(): string
    {
        return $this->renderPartial('@badge');
    }
}
