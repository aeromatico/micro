<?php namespace Aero\Shop\Models;

use Model;

class ShopSettings extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_settings';

    public $fillable = [
        'tenant_id', 'is_enabled', 'base_currency_id', 'inventory_tracking_enabled',
        'guest_checkout_enabled', 'order_number_prefix', 'order_number_sequence',
        'low_stock_threshold', 'store_mode', 'whatsapp_mode', 'whatsapp_number', 'whatsapp_account_id',
        'restaurant_config', 'schedule_mode', 'schedule_hours', 'accepting_orders', 'timezone',
    ];

    public $jsonable = ['restaurant_config', 'schedule_hours'];

    public const ORDER_TYPES = ['dine_in' => 'Comer aquí', 'pickup' => 'Recoger', 'delivery' => 'Delivery'];
    public const DAYS = ['mon' => 'Lunes', 'tue' => 'Martes', 'wed' => 'Miércoles', 'thu' => 'Jueves', 'fri' => 'Viernes', 'sat' => 'Sábado', 'sun' => 'Domingo'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id|unique:aero_shop_settings,tenant_id',
    ];

    public $belongsTo = [
        'tenant'        => [\Aero\Sites\Models\Tenant::class],
        'base_currency' => [Currency::class],
    ];

    public function isWhatsappStore(): bool
    {
        return $this->store_mode === 'whatsapp';
    }

    // Accesores para el formulario de Configuración (campos rc_*).
    public function getRcOrderTypesAttribute() { return $this->restaurant()['order_types']; }
    public function getRcDeliveryFeeAttribute() { return $this->restaurant()['delivery_fee']; }
    public function getRcTablesAttribute() { return $this->restaurant()['tables']; }
    public function getRcAcceptClosedAttribute() { return (bool) $this->restaurant()['accept_closed']; }

    public function isRestaurantStore(): bool
    {
        return $this->store_mode === 'restaurant';
    }

    public static function isRestaurantForTenant(int $tenantId): bool
    {
        return static::where('tenant_id', $tenantId)->value('store_mode') === 'restaurant';
    }

    /** Configuración del restaurante con valores por defecto. */
    public function restaurant(): array
    {
        return array_replace([
            'order_types'      => ['dine_in', 'pickup', 'delivery'],
            'delivery_fee'     => 0,
            'tables'           => 0,
            'accept_closed'    => false,
        ], (array) $this->restaurant_config);
    }

    /** Tipos de pedido habilitados ['dine_in' => 'Comer aquí', ...]. */
    public function enabledOrderTypes(): array
    {
        $enabled = (array) $this->restaurant()['order_types'];

        return array_intersect_key(self::ORDER_TYPES, array_flip($enabled));
    }

    /**
     * Horario general (todos los tipos de tienda):
     *  - always_open: 24 h;  - online: interruptor accepting_orders;
     *  - scheduled: schedule_hours [{day, open, close, closed}], admite turnos
     *    partidos (día repetido) y cierre pasada la medianoche. Hora local del tenant.
     */
    public function isOpenNow(?\Carbon\Carbon $at = null): bool
    {
        $mode = $this->schedule_mode ?: 'always_open';
        if ($mode === 'always_open') {
            return true;
        }
        if ($mode === 'online') {
            return (bool) $this->accepting_orders;
        }

        $hours = (array) $this->schedule_hours;
        $now = ($at ? $at->copy() : now())->setTimezone($this->timezone ?: 'America/La_Paz');
        $today = strtolower(substr($now->format('D'), 0, 3));
        $yesterday = strtolower(substr($now->copy()->subDay()->format('D'), 0, 3));
        $time = $now->format('H:i');

        foreach ($hours as $h) {
            if (!empty($h['closed']) || empty($h['open']) || empty($h['close'])) {
                continue;
            }
            $overnight = $h['close'] <= $h['open'];
            if (($h['day'] ?? '') === $today && $time >= $h['open'] && ($overnight || $time < $h['close'])) {
                return true;
            }
            if ($overnight && ($h['day'] ?? '') === $yesterday && $time < $h['close']) {
                return true;
            }
        }

        return false;
    }

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
