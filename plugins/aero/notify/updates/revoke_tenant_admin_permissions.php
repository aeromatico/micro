<?php

use October\Rain\Database\Updates\Migration;

/**
 * 1.0.1 le concedió a tenant_admin ver/administrar el catálogo, reglas,
 * plantillas y canales de Notify. El catálogo de eventos es genérico para
 * toda la plataforma (no por tenant) y todavía no existe el motor de entrega
 * ni la resolución de audiencias por tenant (fase 2), así que no hay nada que
 * un dueño de tenant deba configurar aquí todavía. Revierte esa concesión.
 */
return new class extends Migration
{
    protected array $keys = [
        'aero.notify.view_events',
        'aero.notify.manage_events',
        'aero.notify.manage_global_rules',
        'aero.notify.manage_rules',
        'aero.notify.manage_templates',
        'aero.notify.manage_channels',
        'aero.notify.view_deliveries',
        'aero.notify.resend',
        'aero.notify.send_test',
    ];

    public function up(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();

        if (!$role) {
            return;
        }

        $permissions = $role->permissions ?? [];

        foreach ($this->keys as $key) {
            unset($permissions[$key]);
        }

        $role->permissions = $permissions;
        $role->save();
    }

    public function down(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();

        if (!$role) {
            return;
        }

        $permissions = $role->permissions ?? [];

        foreach ($this->keys as $key) {
            $permissions[$key] = 1;
        }

        $role->permissions = $permissions;
        $role->save();
    }
};
