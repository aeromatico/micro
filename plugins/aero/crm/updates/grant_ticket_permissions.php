<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    protected array $permissions = [
        'aero.crm.manage_tickets' => 1,
        'aero.crm.manage_departments' => 1,
    ];

    public function up(): void
    {
        foreach (['tenant_admin', 'superadmin'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }

            $role->permissions = array_merge($role->permissions ?? [], $this->permissions);
            $role->save();
        }
    }

    public function down(): void
    {
        foreach (['tenant_admin', 'superadmin'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }

            $perms = $role->permissions ?? [];
            foreach (array_keys($this->permissions) as $key) {
                unset($perms[$key]);
            }
            $role->permissions = $perms;
            $role->save();
        }
    }
};
