<?php

use October\Rain\Database\Updates\Migration;

/**
 * El tenant participa en la Comunidad desde el panel (foro, sugerencias y
 * soporte rápido). La moderación sigue siendo del superadmin.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.community.use',
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\Community\Plugin::class)) {
            return;
        }

        foreach (['tenant_admin', 'tenant_admin_pro'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }
            $role->permissions = array_merge($role->permissions ?? [], array_fill_keys($this->permissions, 1));
            $role->save();
        }
    }

    public function down(): void
    {
        foreach (['tenant_admin', 'tenant_admin_pro'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }
            $perms = $role->permissions ?? [];
            foreach ($this->permissions as $permission) {
                unset($perms[$permission]);
            }
            $role->permissions = $perms;
            $role->save();
        }
    }
};
