<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    protected array $shopPermissions = [
        'aero.shop.manage_products'         => 1,
        'aero.shop.manage_collections'      => 1,
        'aero.shop.manage_orders'           => 1,
        'aero.shop.manage_inventory'        => 1,
        'aero.shop.manage_payment_gateways' => 1,
        'aero.shop.manage_settings'         => 1,
    ];

    public function up(): void
    {
        foreach (['tenant_admin', 'superadmin'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }

            $role->permissions = array_merge($role->permissions ?? [], $this->shopPermissions);
            $role->save();
        }
    }

    public function down(): void
    {
        foreach (['tenant_admin', 'superadmin'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }

            $perms = $role->permissions ?? [];
            foreach (array_keys($this->shopPermissions) as $key) {
                unset($perms[$key]);
            }
            $role->permissions = $perms;
            $role->save();
        }
    }
};
