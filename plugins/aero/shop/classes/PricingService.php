<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\TenantCurrency;

class PricingService
{
    /**
     * Convierte un precio en la moneda base del tenant a la moneda destino,
     * usando la tasa de cambio configurada en aero_shop_tenant_currencies.
     */
    public function convert(int $tenantId, float $basePrice, int $currencyId): float
    {
        $tenantCurrency = TenantCurrency::forTenant($tenantId)->where('currency_id', $currencyId)->first();
        if (!$tenantCurrency || $tenantCurrency->is_default) {
            return $basePrice;
        }
        return round($basePrice * (float) $tenantCurrency->exchange_rate, 4);
    }
}
