<?php

use October\Rain\Database\Updates\Migration;

/**
 * Habilita el resto del contenido de Hello (bandeja, llamadas, contactos,
 * plantillas, campañas, publicaciones) para el rol tenant_admin: cada tenant
 * gestiona su propio contenido de mensajería, mientras la plataforma se queda
 * solo con las conexiones (perfiles/números y cuentas conectadas). Guardado
 * por class_exists porque Sites no depende de Hello.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.hello.manage_conversations',
        'aero.hello.manage_calls',
        'aero.hello.manage_contacts',
        'aero.hello.manage_templates',
        'aero.hello.manage_campaigns',
        'aero.hello.manage_posts',
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
