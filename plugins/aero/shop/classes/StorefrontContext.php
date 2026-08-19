<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\Currency;
use Aero\Shop\Models\ShopSettings;
use Aero\Sites\Models\Tenant;

/**
 * Resuelve tenant/configuración de tienda una sola vez por request
 * (memoizado en request()->attributes), evitando que cada componente
 * de la página dispare sus propias queries redundantes — ver
 * Tenant::resolveFromDomain(), llamado hoy de forma independiente por
 * cada componente CMS (TenantSeo, PageList, PageDetail, ContactSection).
 */
class StorefrontContext
{
    public static function tenant(): ?Tenant
    {
        $cached = request()->attributes->get('aero_shop_tenant');
        if ($cached) {
            return $cached;
        }

        $tenant = Tenant::resolveFromDomain(request()->getHost());
        if ($tenant) {
            request()->attributes->set('aero_shop_tenant', $tenant);
        }

        return $tenant;
    }

    public static function settings(): ?ShopSettings
    {
        $tenant = self::tenant();
        if (!$tenant) {
            return null;
        }

        $key = 'aero_shop_settings_' . $tenant->id;
        $cached = request()->attributes->get($key);
        if ($cached) {
            return $cached;
        }

        $settings = ShopSettings::where('tenant_id', $tenant->id)->first();
        if ($settings) {
            request()->attributes->set($key, $settings);
        }

        return $settings;
    }

    public static function isEnabled(): bool
    {
        return (bool) self::settings()?->is_enabled;
    }

    public static function currency(): ?Currency
    {
        $settings = self::settings();
        if ($settings?->base_currency) {
            return $settings->base_currency;
        }

        return Currency::where('code', 'BOB')->first();
    }
}
