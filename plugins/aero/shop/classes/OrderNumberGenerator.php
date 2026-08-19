<?php namespace Aero\Shop\Classes;

use Aero\Shop\Models\ShopSettings;
use Db;

class OrderNumberGenerator
{
    public function generate(int $tenantId): string
    {
        return Db::transaction(function () use ($tenantId) {
            $settings = ShopSettings::query()->lockForUpdate()->where('tenant_id', $tenantId)->firstOrFail();
            $settings->order_number_sequence += 1;
            $settings->save();

            $prefix = $settings->order_number_prefix ?: 'ORD-';
            return $prefix . str_pad((string) $settings->order_number_sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
