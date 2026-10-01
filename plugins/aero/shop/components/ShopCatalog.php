<?php namespace Aero\Shop\Components;

use Aero\Shop\Classes\StorefrontContext;
use Aero\Shop\Models\Collection;
use Aero\Shop\Models\Product;
use Cms\Classes\ComponentBase;

class ShopCatalog extends ComponentBase
{
    public $products = null;
    public array $collections = [];
    public ?Collection $activeCollection = null;
    public ?\Aero\Shop\Models\Currency $currency = null;
    public bool $shopEnabled = false;
    public bool $restaurantStore = false;
    public array $menu = [];
    public array $restaurant = [];
    public bool $isOpen = true;
    public ?string $table = null;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Shop Catálogo',
            'description' => 'Grilla de productos publicados del tenant, con filtro por colección.',
        ];
    }

    public function defineProperties(): array
    {
        return [
            'perPage' => [
                'title'   => 'Productos por página',
                'type'    => 'string',
                'default' => '12',
            ],
            'collectionSlug' => [
                'title'       => 'Slug de colección',
                'description' => 'Filtra el catálogo por colección. Normalmente {{ :coleccion }} desde la URL.',
                'type'        => 'string',
                'default'     => '',
            ],
        ];
    }

    public function onRun()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return $this->controller->run('404');
        }

        $this->shopEnabled = StorefrontContext::isEnabled();
        if (!$this->shopEnabled) {
            return $this->controller->run('404');
        }

        $this->currency = StorefrontContext::currency();

        $settings = StorefrontContext::settings();
        $this->isOpen = $settings ? $settings->isOpenNow() : true;
        if ($settings?->isRestaurantStore()) {
            $this->buildRestaurantMenu($tenant, $settings);
            return;
        }

        $this->collections = Collection::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->toArray();

        $query = Product::forTenant($tenant->id)
            ->active()
            ->with(['images', 'collection'])
            ->orderByDesc('is_featured')
            ->orderByDesc('published_at');

        // El slug llega por la URL amigable /tienda/:coleccion. Se acepta ?coleccion=
        // como alias para no romper enlaces antiguos, que se redirigen abajo.
        $collectionSlug = trim((string) $this->property('collectionSlug')) ?: (string) get('coleccion');

        if ($collectionSlug !== '') {
            $this->activeCollection = Collection::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->where('slug', $collectionSlug)
                ->first();

            if (!$this->activeCollection) {
                return $this->controller->run('404');
            }

            // Enlace antiguo ?coleccion=x: redirige permanente a /tienda/x
            if (!$this->property('collectionSlug')) {
                return redirect('/tienda/' . $this->activeCollection->slug, 301);
            }

            $query->where('collection_id', $this->activeCollection->id);
        }

        $perPage = max(1, min(48, (int) $this->property('perPage', 12)));
        $this->products = $query->paginate($perPage);
    }

    /**
     * Carta del restaurante: todos los platos activos agrupados por categoría
     * (colección), con extras y variantes listos para la hoja de selección.
     * La mesa llega por el QR (?mesa=N) y se recuerda en sesión.
     */
    protected function buildRestaurantMenu($tenant, $settings): void
    {
        $this->restaurantStore = true;
        $this->restaurant = $settings->restaurant();

        $mesa = trim((string) get('mesa'));
        if ($mesa !== '' && preg_match('/^[\p{L}\p{N} _-]{1,30}$/u', $mesa)) {
            session(['aero_shop_table_' . $tenant->id => $mesa]);
        }
        $this->table = session('aero_shop_table_' . $tenant->id);

        $groupsByProduct = \Aero\Shop\Models\ModifierGroup::forTenant($tenant->id)->orderBy('sort_order')->get()->groupBy('product_id');

        $products = Product::forTenant($tenant->id)->active()
            ->with(['images', 'collection', 'variants.option_values'])
            ->orderByDesc('is_featured')->orderBy('name')->get();

        $sections = [];
        foreach ($products as $product) {
            $key = $product->collection?->id ?? 0;
            $sections[$key] ??= [
                'id' => 'cat-' . $key, 'name' => $product->collection?->name ?? 'Otros',
                'sort' => $product->collection?->sort_order ?? 9999, 'products' => [],
            ];

            $sections[$key]['products'][] = [
                'id'          => $product->id,
                'name'        => $product->name,
                'description' => $product->description,
                'price'       => (float) $product->display_price,
                'from'        => (bool) $product->has_price_range,
                'in_stock'    => (bool) $product->is_in_stock,
                'featured'    => (bool) $product->is_featured,
                'prep'        => $product->prep_minutes ? (int) $product->prep_minutes : null,
                'min_qty'     => max(1, (int) $product->min_quantity),
                'image'       => $product->images->first()?->getThumbUrl(400, 400, ['mode' => 'crop']),
                'variants'    => $product->has_variants
                    ? $product->variants->where('is_active', true)->map(fn ($v) => ['id' => $v->id, 'label' => $v->label, 'price' => (float) $v->price])->values()->all()
                    : [],
                'groups'      => ($groupsByProduct[$product->id] ?? collect())->map(fn ($g) => [
                    'name' => $g->name, 'min' => (int) $g->min_select, 'max' => (int) $g->max_select,
                    'choices' => collect($g->choices)->where('is_active', true)->map(fn ($c) => [
                        'uid' => $c['uid'], 'name' => $c['name'], 'price' => (float) $c['price_delta'],
                    ])->values()->all(),
                ])->values()->all(),
            ];
        }

        usort($sections, fn ($a, $b) => $a['sort'] <=> $b['sort'] ?: strcmp($a['name'], $b['name']));
        $this->menu = $sections;
    }
}
