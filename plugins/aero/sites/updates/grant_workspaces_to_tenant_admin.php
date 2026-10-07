<?php

use October\Rain\Database\Updates\Migration;

/**
 * El tenant ve el Mercado y la Oficina de agentes de Workspaces (contratar y
 * enviar encargos). Todo aislado por tenant desde el servidor: el catálogo y los
 * prompts siguen siendo del superadmin. Guardado por class_exists porque Sites
 * no depende de Workspaces.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.workspaces.use',
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\Workspaces\Models\Staff::class)) {
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
