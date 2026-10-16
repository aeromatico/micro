<?php

use October\Rain\Database\Updates\Migration;

/**
 * Marketplace de la tienda: el tenant administra comerciantes y cada
 * comerciante ve «Mis ventas». Todos los miembros del equipo comparten el rol
 * tenant_admin, así que el acceso real se restringe por código (Seller).
 * Guardado por class_exists porque Sites no depende de Shop.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.shop.manage_sellers',
        'aero.shop.sell',
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\Shop\Plugin::class)) {
            return;
        }

        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $role->permissions = array_merge($role->permissions ?? [], array_fill_keys($this->permissions, 1));
        $role->save();
    }

    public function down(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $perms = $role->permissions ?? [];
        foreach ($this->permissions as $permission) {
            unset($perms[$permission]);
        }
        $role->permissions = $perms;
        $role->save();
    }
};
