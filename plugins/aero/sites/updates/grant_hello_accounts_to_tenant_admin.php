<?php

use October\Rain\Database\Updates\Migration;

/**
 * Cierra el último pedazo de Hello que seguía siendo "solo plataforma": las
 * conexiones. grant_hello_content_to_tenant_admin.php dejó a propósito
 * perfiles/cuentas conectadas para el superadmin, pero cada tenant conecta su
 * propio número (pantalla Conectar, con QR de WhatsApp Web o alta manual de
 * Cloud API). manage_accounts habilita Conectar y Cuentas conectadas, ambas
 * aisladas por tenant vía ScopesToOwner en Hello, así que el tenant solo ve y
 * toca lo suyo. Perfiles sigue siendo superadmin. Guardado por class_exists
 * porque Sites no depende de Hello.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.hello.manage_accounts',
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
