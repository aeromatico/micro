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
    public $activeOrders = null;
    public string $timezone = 'America/La_Paz';

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
        $this->timezone = $settings->timezone ?: 'America/La_Paz';
        $this->activeOrders = $this->loadActiveOrders($tenant->id, []);

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

    /**
     * Pedidos del cliente que siguen en curso (últimas 24 h): los de su sesión
     * más los tokens que el navegador recuerda en localStorage. El token es el
     * secreto del pedido, así que solo se listan los que el cliente ya posee.
     */
    protected function loadActiveOrders(int $tenantId, array $extraTokens)
    {
        $tokens = array_values(array_unique(array_filter(array_merge(
            (array) session('aero_shop_orders_' . $tenantId, []),
            array_map('strval', $extraTokens)
        ), fn ($t) => is_string($t) && strlen($t) === 40)));

        if (!$tokens) {
            return collect();
        }

        return \Aero\Shop\Models\Order::forTenant($tenantId)
            ->whereIn('access_token', array_slice($tokens, 0, 8))
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDay())
            ->where(fn ($q) => $q->whereIn('kitchen_status', ['new', 'preparing', 'ready'])
                // Recién entregados (2 h): siguen a la vista por si el cliente quiere revisar el detalle.
                ->orWhere(fn ($q3) => $q3->where('kitchen_status', 'delivered')->where('kitchen_updated_at', '>=', now()->subHours(2)))
                ->orWhere(fn ($q2) => $q2->whereNull('kitchen_status')->whereIn('status', ['pending', 'awaiting_payment', 'paid'])))
            ->orderByDesc('created_at')->limit(3)->get();
    }

    /** El navegador envía los tokens que recuerda; se devuelve el bloque «Tus pedidos» actualizado. */
    public function onActiveOrders()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return [];
        }

        $tokens = array_slice(array_filter(explode(',', (string) post('tokens'))), 0, 8);
        $this->activeOrders = $this->loadActiveOrders($tenant->id, $tokens);
        $this->timezone = StorefrontContext::settings()?->timezone ?: 'America/La_Paz';

        return ['#active-orders' => $this->renderPartial('@activeorders')];
    }

    /** Consulta de un pedido con número + celular (para quien perdió el enlace o cambió de dispositivo). */
    public function onFindOrder()
    {
        $tenant = StorefrontContext::tenant();
        if (!$tenant) {
            return [];
        }

        $key = 'shop-find-order:' . request()->ip();
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($key, 6)) {
            return ['#find-order-error' => '<p class="text-sm text-red-500">Demasiados intentos. Espera un minuto e inténtalo de nuevo.</p>'];
        }
        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);

        $number = strtoupper(trim((string) post('order_number')));
        $phone = \Aero\Shop\Classes\WhatsappCheckout::normalizePhone(post('phone'));
        $fail = ['#find-order-error' => '<p class="text-sm text-red-500">No encontramos un pedido con esos datos. Revisa el número (ej. PED-000007) y el celular con el que lo hiciste.</p>'];
        if ($number === '' || !$phone) {
            return $fail;
        }

        $order = \Aero\Shop\Models\Order::forTenant($tenant->id)->where('order_number', $number)->with('customer')->first();
        $orderPhone = \Aero\Shop\Classes\WhatsappCheckout::normalizePhone($order?->customer?->phone);
        if (!$order || !$orderPhone || $orderPhone !== $phone) {
            return $fail;
        }

        \Illuminate\Support\Facades\RateLimiter::clear($key);

        return \Redirect::to('/tienda/pedido/' . $order->access_token);
    }
}
