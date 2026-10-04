<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    protected array $posPermissions = [
        'aero.pos.use'           => 1,
        'aero.pos.discount'      => 1,
        'aero.pos.void'          => 1,
        'aero.pos.manage_shifts' => 1,
        'aero.pos.reports'       => 1,
        'aero.pos.manage'        => 1,
    ];

    public function up(): void
    {
        foreach (['tenant_admin', 'tenant_admin_pro', 'superadmin'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }
            $role->permissions = array_merge($role->permissions ?? [], $this->posPermissions);
            $role->save();
        }
    }

    public function down(): void
    {
        foreach (['tenant_admin', 'tenant_admin_pro', 'superadmin'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }
            $perms = $role->permissions ?? [];
            foreach (array_keys($this->posPermissions) as $key) {
                unset($perms[$key]);
            }
            $role->permissions = $perms;
            $role->save();
        }
    }
};
