<?php

use October\Rain\Database\Updates\Migration;

/**
 * El tenant configura su puente de Livechat a WhatsApp. Guardado por
 * class_exists porque Sites no depende de Livechat.
 */
return new class extends Migration
{
    protected array $permissions = ['aero.livechat.manage_settings'];

    public function up(): void
    {
        if (!class_exists(\Aero\Livechat\Plugin::class)) {
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
