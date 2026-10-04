<?php namespace Aero\Pos\Classes;

use Aero\Pos\Models\PaymentMethod;
use Aero\Pos\Models\PosSettings;
use Aero\Pos\Models\Terminal;

/**
 * Crea lo mínimo para vender: ajustes, una terminal y los métodos de pago
 * básicos. Es idempotente y se llama al abrir cualquier pantalla del POS, así que
 * un tenant nuevo nunca se encuentra con listas vacías.
 */
class PosProvisioner
{
    public const DEFAULT_METHODS = [
        ['code' => 'efectivo',      'label' => 'Efectivo',      'kind' => 'cash',     'sort_order' => 1],
        ['code' => 'qr',            'label' => 'QR',            'kind' => 'qr',       'sort_order' => 2],
        ['code' => 'tarjeta',       'label' => 'Tarjeta',       'kind' => 'card',     'sort_order' => 3],
        ['code' => 'transferencia', 'label' => 'Transferencia', 'kind' => 'transfer', 'sort_order' => 4],
    ];

    public static function ensureDefaults(int $tenantId): PosSettings
    {
        $settings = PosSettings::forTenant($tenantId)->first();
        if (!$settings) {
            $settings = new PosSettings(['tenant_id' => $tenantId]);
            // Si la tienda ya es de restaurante, el perfil arranca así; de lo contrario, comercio.
            $profile = \Aero\Shop\Models\ShopSettings::isRestaurantForTenant($tenantId) ? 'restaurant' : 'retail';
            $settings->applyPreset($profile);
            $settings->save();
        }

        if (!Terminal::forTenant($tenantId)->exists()) {
            Terminal::create(['tenant_id' => $tenantId, 'name' => 'Caja 1', 'code' => 'caja-1', 'is_active' => true]);
        }

        foreach (self::DEFAULT_METHODS as $m) {
            PaymentMethod::firstOrCreate(['tenant_id' => $tenantId, 'code' => $m['code']], $m + ['is_active' => true]);
        }

        return $settings;
    }
}
