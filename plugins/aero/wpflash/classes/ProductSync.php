<?php namespace Aero\WpFlash\Classes;

use Event;
use Log;
use Str;
use Aero\WpFlash\Models\ProductLink;

/**
 * WooCommerce manda siempre: cada llamada sobrescribe el producto de
 * Aero.Shop con lo que venga de WP, sin merge ni preguntas — igual que
 * hub/classes/CatalogSync.php upsertea por llave natural. Si Aero.Shop no
 * está instalado, no hace nada (WP Flash sigue sirviendo solo para el
 * enrutamiento de dominio).
 */
class ProductSync
{
    public static function handle(int $tenantId, string $topic, array $payload): void
    {
        if (!class_exists(\Aero\Shop\Models\Product::class)) {
            return;
        }

        $wpProductId = $payload['id'] ?? null;
        if (!$wpProductId) {
            return;
        }

        if (str_ends_with($topic, '.deleted')) {
            static::delete($tenantId, (int) $wpProductId);
            return;
        }

        static::upsert($tenantId, $payload);
    }

    protected static function upsert(int $tenantId, array $payload): void
    {
        $wpProductId = (int) $payload['id'];

        $link = ProductLink::firstOrNew(['tenant_id' => $tenantId, 'wp_product_id' => $wpProductId]);

        $product = $link->shop_product_id
            ? \Aero\Shop\Models\Product::forTenant($tenantId)->find($link->shop_product_id)
            : null;

        $isNew = !$product;
        $product = $product ?: new \Aero\Shop\Models\Product(['tenant_id' => $tenantId]);

        $product->name             = $payload['name'] ?? $product->name ?? "Producto WP #{$wpProductId}";
        $product->sku              = $payload['sku'] ?: null;
        $product->description      = $payload['description'] ?? $payload['short_description'] ?? null;
        $product->base_price       = static::price($payload['regular_price'] ?? $payload['price'] ?? 0);
        $product->compare_at_price = static::price($payload['sale_price'] ?? null);
        $product->track_inventory  = ($payload['manage_stock'] ?? false) ? true : false;
        $product->stock_quantity   = (int) ($payload['stock_quantity'] ?? 0);
        $product->status           = ($payload['status'] ?? 'draft') === 'publish' ? 'active' : 'draft';
        $product->collection_id    = static::resolveCollection($tenantId, $payload['categories'] ?? []);
        $product->save();

        if ($isNew && !empty($payload['images'][0]['src'])) {
            static::attachFirstImage($product, $payload['images'][0]['src']);
        }

        $link->fill([
            'shop_product_id' => $product->id,
            'sku'             => $product->sku,
            'wp_updated_at'   => $payload['date_modified'] ?? null,
            'last_synced_at'  => now(),
        ]);
        $link->save();

        Event::fire('aero.wpflash.productSynced', [$product]);
    }

    protected static function delete(int $tenantId, int $wpProductId): void
    {
        $link = ProductLink::where('tenant_id', $tenantId)->where('wp_product_id', $wpProductId)->first();
        if (!$link) {
            return;
        }

        if ($link->shop_product_id && ($product = \Aero\Shop\Models\Product::find($link->shop_product_id))) {
            $product->status = 'archived';
            $product->save();
        }

        $link->delete();
    }

    protected static function price(mixed $value): ?float
    {
        return ($value === '' || $value === null) ? null : (float) $value;
    }

    protected static function resolveCollection(int $tenantId, array $categories): ?int
    {
        if (!class_exists(\Aero\Shop\Models\Collection::class) || empty($categories[0]['name'])) {
            return null;
        }

        return \Aero\Shop\Models\Collection::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $categories[0]['name']],
            ['slug' => Str::slug($categories[0]['name'])]
        )->id;
    }

    protected static function attachFirstImage($product, string $url): void
    {
        try {
            $file = new \System\Models\File();
            $file->fromUrl($url);
            $product->images()->add($file);
        }
        catch (\Throwable $e) {
            Log::warning('WpFlash: no se pudo descargar la imagen del producto: ' . $e->getMessage());
        }
    }
}
