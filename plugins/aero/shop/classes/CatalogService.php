<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\Product;
use Aero\Shop\Models\ShopSettings;

/** Lectura del catálogo de un tenant, compartida por la API REST y el chat. */
class CatalogService
{
    public function search(int $tenantId, ?string $q = null, ?int $collectionId = null, int $perPage = 20)
    {
        return Product::forTenant($tenantId)->active()->with(['images', 'variants' => fn ($v) => $v->where('is_active', true)->with('option_values')])
            ->when($q, fn ($query, $q) => $query->where(fn ($w) => $w->where('name', 'like', '%' . $q . '%')->orWhere('sku', 'like', '%' . $q . '%')))
            ->when($collectionId, fn ($query, $id) => $query->where('collection_id', $id))
            ->orderBy('name')->paginate(min(100, max(1, $perPage)));
    }

    public function find(int $tenantId, int $id): ?Product
    {
        return Product::forTenant($tenantId)->active()->with(['images', 'variants' => fn ($v) => $v->where('is_active', true)->with('option_values')])->find($id);
    }

    /** Forma pública de un producto (nada interno: costo, borradores, etc.). */
    public static function present(Product $p): array
    {
        $tracks = ShopSettings::inventoryEnabledForTenant($p->tenant_id) && $p->track_inventory && !$p->allow_backorder;
        $image = $p->images->first();

        return [
            'id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'sku' => $p->sku, 'type' => $p->type,
            'description' => $p->description ? mb_substr(strip_tags($p->description), 0, 280) : null,
            'price' => $p->display_price, 'has_price_range' => (bool) $p->has_price_range, 'compare_at_price' => $p->compare_at_price ? (float) $p->compare_at_price : null,
            'has_variants' => (bool) $p->has_variants, 'requires_shipping' => (bool) $p->requires_shipping,
            'in_stock' => (bool) $p->is_in_stock, 'stock' => $tracks ? $p->display_stock : null,
            'image_url' => $image ? $image->path : null, 'collection_id' => $p->collection_id,
            'variants' => $p->has_variants ? $p->variants->map(fn ($v) => [
                'id' => $v->id, 'label' => $v->label, 'sku' => $v->sku, 'price' => (float) $v->price,
                'stock' => $tracks ? (int) $v->stock_quantity : null, 'in_stock' => !$tracks || $v->stock_quantity > 0,
            ])->values()->all() : [],
        ];
    }
}
