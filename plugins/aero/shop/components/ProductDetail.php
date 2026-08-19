<?php namespace Aero\Shop\Components;

use Aero\Shop\Classes\StorefrontContext;
use Aero\Shop\Models\Product;
use Cms\Classes\ComponentBase;

class ProductDetail extends ComponentBase
{
    public ?Product $product = null;
    public ?\Aero\Shop\Models\Currency $currency = null;
    public array $optionsData = [];
    public array $variantsData = [];
    public bool $inventoryEnabled = true;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Shop Detalle de Producto',
            'description' => 'Muestra un producto con selector de variantes y botón de agregar al carrito.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'slug' => [
                'title'   => 'Slug',
                'type'    => 'string',
                'default' => ':slug',
            ],
        ];
    }

    public function onRun()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant || !StorefrontContext::isEnabled()) {
            return $this->controller->run('404');
        }

        $this->currency = StorefrontContext::currency();
        $this->inventoryEnabled = \Aero\Shop\Models\ShopSettings::inventoryEnabledForTenant($tenant->id);

        $this->product = Product::forTenant($tenant->id)
            ->active()
            ->where('slug', $this->property('slug'))
            ->with(['images', 'collection', 'options.values', 'variants.option_values'])
            ->first();

        if (!$this->product) {
            return $this->controller->run('404');
        }

        if ($this->product->has_variants) {
            $this->buildVariantSelectorData();
        }
    }

    /**
     * Arma la data que consume Alpine.js en el partial para resolver, sin
     * requests adicionales, qué variante corresponde a la combinación de
     * opciones elegida (ej. Talla=M + Color=Rojo -> SKU/precio/stock exactos).
     */
    protected function buildVariantSelectorData(): void
    {
        $this->optionsData = $this->product->options->map(fn ($option) => [
            'id'     => $option->id,
            'name'   => $option->name,
            'values' => $option->values->map(fn ($v) => ['id' => $v->id, 'value' => $v->value])->all(),
        ])->all();

        $this->variantsData = $this->product->variants
            ->where('is_active', true)
            ->map(function ($variant) {
                $valueIds = $variant->option_values->pluck('id')->sort()->values()->all();
                return [
                    'key'      => implode(',', $valueIds),
                    'id'       => $variant->id,
                    'sku'      => $variant->sku,
                    'price'    => (float) $variant->price,
                    'stock'    => (int) $variant->stock_quantity,
                    'label'    => $variant->label,
                    'image'    => $variant->image?->getThumbUrl(600, 600, ['mode' => 'crop']),
                ];
            })
            ->values()
            ->all();
    }
}
