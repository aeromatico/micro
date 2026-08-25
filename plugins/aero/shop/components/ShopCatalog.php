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
}
