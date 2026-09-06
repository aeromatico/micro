<?php

use October\Rain\Database\Updates\Migration;

/**
 * Habilita la gestión de API keys del gateway Aero.Api (tab "Configuración")
 * para el rol tenant_admin, aislada a las suyas por Plugin::bootApiIntegration().
 * Guardado por class_exists porque Sites no depende de Api: si no está
 * instalado, no hay nada que otorgar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(\Aero\Api\Models\ApiKey::class)) {
            return;
        }

        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $role->permissions = array_merge($role->permissions ?? [], [
            'aero.api.manage_keys' => 1,
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
        unset($perms['aero.api.manage_keys']);
        $role->permissions = $perms;
        $role->save();
    }
};
