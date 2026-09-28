<?php namespace Aero\WpFlash\Models;

use Model;

/**
 * Mapeo (tenant_id, wp_customer_id) → Aero\Shop\Models\Customer. Ver
 * ProductLink para por qué es una tabla puente y no un external_id en Shop.
 */
class CustomerLink extends Model
{
    public $table = 'aero_wpflash_customer_links';

    public $fillable = ['tenant_id', 'wp_customer_id', 'shop_customer_id', 'email', 'last_synced_at'];

    public $dates = ['last_synced_at'];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function shopCustomer()
    {
        if (!class_exists(\Aero\Shop\Models\Customer::class)) {
            return null;
        }

        return \Aero\Shop\Models\Customer::find($this->shop_customer_id);
    }
}
