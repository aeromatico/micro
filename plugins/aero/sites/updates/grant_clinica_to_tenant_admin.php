<?php

use October\Rain\Database\Updates\Migration;

/**
 * El tenant usa Aero.Clinica (clínica de medicina humana) desde el panel. Lo global
 * (aero.clinica.superadmin) sigue siendo de la plataforma. Guardado por
 * class_exists porque Sites no depende de Clinica.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.clinica.use',
        'aero.clinica.settings',
        'aero.clinica.reception',
        'aero.clinica.professional',
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\Clinica\Plugin::class)) {
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
