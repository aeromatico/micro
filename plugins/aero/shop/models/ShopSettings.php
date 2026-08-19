<?php namespace Aero\Shop\Models;

use Model;

class ShopSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_settings';

    public $fillable = [
        'tenant_id', 'is_enabled', 'base_currency_id', 'inventory_tracking_enabled',
        'guest_checkout_enabled', 'order_number_prefix', 'order_number_sequence',
        'low_stock_threshold',
    ];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id|unique:aero_shop_settings,tenant_id',
    ];

    public $belongsTo = [
        'tenant'        => [\Aero\Sites\Models\Tenant::class],
        'base_currency' => [Currency::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    protected static array $inventoryEnabledCache = [];

    /**
     * Switch maestro por tenant: si está apagado, InventoryService no valida
     * ni descuenta stock para ningún producto, sin importar su track_inventory
     * individual. Sin fila de settings (tenant nunca visitó Configuración de
     * tienda), se asume activado — mismo default que la columna en BD.
     */
    public static function inventoryEnabledForTenant(int $tenantId): bool
    {
        if (!array_key_exists($tenantId, static::$inventoryEnabledCache)) {
            $value = static::where('tenant_id', $tenantId)->value('inventory_tracking_enabled');
            static::$inventoryEnabledCache[$tenantId] = $value === null ? true : (bool) $value;
        }

        return static::$inventoryEnabledCache[$tenantId];
    }
}
