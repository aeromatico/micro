<?php

use October\Rain\Database\Updates\Migration;

/**
 * Permite al tenant elegir su canal de WhatsApp por defecto (Configuración →
 * Mensajería). Guardado por class_exists porque Sites no depende de Hello.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.hello.manage_settings',
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\Hello\Models\ApiKey::class)) {
            return;
        }

        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $granted = array_fill_keys($this->permissions, 1);
        $role->permissions = array_merge($role->permissions ?? [], $granted);
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
