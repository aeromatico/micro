<?php

use October\Rain\Database\Updates\Migration;

/**
 * El tenant usa Aero.Office (reservas) desde el panel: administración,
 * configuración, recepción y vista de profesional. Lo global sigue siendo del
 * superadmin. Guardado por class_exists porque Sites no depende de Office.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.office.use',
        'aero.office.settings',
        'aero.office.reception',
        'aero.office.professional',
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\Office\Plugin::class)) {
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
