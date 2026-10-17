<?php

use October\Rain\Database\Updates\Migration;

/**
 * El tenant usa la Ayuda guiada desde el panel (botón flotante de Ayuda). Lo
 * global (temas, flujos, búsquedas) sigue siendo del superadmin. Guardado por
 * class_exists porque Sites no depende de Help.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.help.use',
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\Help\Plugin::class)) {
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
