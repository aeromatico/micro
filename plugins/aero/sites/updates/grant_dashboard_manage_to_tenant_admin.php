<?php

use October\Rain\Database\Updates\Migration;

/**
 * Permite que el tenant edite SU layout personal del dashboard (el editor del
 * widget solo aparece con dashboard.manage). Los cambios van a la preferencia
 * del usuario; Plugin::bootDashboardGuards() impide que toque el layout
 * global o la pantalla de gestión de dashboards.
 */
return new class extends Migration
{
    protected array $roles = ['tenant_admin', 'tenant_admin_pro'];

    public function up(): void
    {
        foreach ($this->roles as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }

            $role->permissions = array_merge($role->permissions ?? [], ['dashboard.manage' => 1]);
            $role->save();
        }
    }

    public function down(): void
    {
        foreach ($this->roles as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }

            $perms = $role->permissions ?? [];
            unset($perms['dashboard.manage']);
            $role->permissions = $perms;
            $role->save();
        }
    }
};
