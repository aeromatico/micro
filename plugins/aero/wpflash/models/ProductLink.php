<?php namespace Aero\WpFlash\Models;

use Model;

/**
 * Mapeo (tenant_id, wp_product_id) → Aero\Shop\Models\Product, en tabla
 * puente propia en vez de un external_id agregado a aero_shop_products —
 * mismo idioma que hello/ContactIdentity y tracking/Job (external_type +
 * external_id), y evita tocar el plugin Aero.Shop.
 */
class ProductLink extends Model
{
    public $table = 'aero_wpflash_product_links';

    public $fillable = ['tenant_id', 'wp_product_id', 'shop_product_id', 'sku', 'wp_updated_at', 'last_synced_at'];

    public $dates = ['wp_updated_at', 'last_synced_at'];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Producto de Aero.Shop vinculado, si el plugin está instalado. */
    public function shopProduct()
    {
        if (!class_exists(\Aero\Shop\Models\Product::class)) {
            return null;
        }

        return \Aero\Shop\Models\Product::find($this->shop_product_id);
    }
}
