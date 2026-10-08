<?php

use October\Rain\Database\Updates\Migration;

/**
 * El tenant usa {Plugin} desde el panel. Lo global sigue siendo del
 * superadmin. Guardado por class_exists porque Sites no depende de {Plugin}.
 * Copiar a plugins/aero/sites/updates/grant_{p}_to_tenant_admin.php y añadir
 * la versión en el version.yaml de aero/sites.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.{p}.use',
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\{Plugin}\Plugin::class)) {
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
