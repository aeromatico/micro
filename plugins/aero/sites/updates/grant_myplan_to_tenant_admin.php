<?php

use October\Rain\Database\Updates\Migration;

/**
 * El tenant_admin ve «Mi plan» (vigencia, QR de renovación y pagos).
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.sites.myplan',
    ];

    public function up(): void
    {
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
