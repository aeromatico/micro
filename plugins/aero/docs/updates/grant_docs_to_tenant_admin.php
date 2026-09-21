<?php

use October\Rain\Database\Updates\Migration;

/** Los tenants administran su propia documentación (solo ven la de su ámbito). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tenant_admin', 'tenant_admin_pro'] as $code) {
            if ($role = \Backend\Models\UserRole::where('code', $code)->first()) {
                $role->permissions = array_merge($role->permissions ?? [], ['aero.docs.manage' => 1]);
                $role->save();
            }
        }
    }

    public function down(): void
    {
        foreach (['tenant_admin', 'tenant_admin_pro'] as $code) {
            if ($role = \Backend\Models\UserRole::where('code', $code)->first()) {
                $perms = $role->permissions ?? [];
                unset($perms['aero.docs.manage']);
                $role->permissions = $perms;
                $role->save();
            }
        }
    }
};
