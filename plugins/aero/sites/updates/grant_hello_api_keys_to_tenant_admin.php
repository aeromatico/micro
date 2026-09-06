<?php

use October\Rain\Database\Updates\Migration;

/**
 * Habilita el ítem de menú "Mensajería" (self-service de API keys de
 * Aero.Hello) para el rol tenant_admin. Guardado por class_exists porque
 * Sites no depende de Hello: si no está instalado, no hay nada que otorgar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(\Aero\Hello\Models\ApiKey::class)) {
            return;
        }

        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $role->permissions = array_merge($role->permissions ?? [], [
            'aero.hello.manage_api_keys' => 1,
        ]);
        $role->save();
    }

    public function down(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $perms = $role->permissions ?? [];
        unset($perms['aero.hello.manage_api_keys']);
        $role->permissions = $perms;
        $role->save();
    }
};
