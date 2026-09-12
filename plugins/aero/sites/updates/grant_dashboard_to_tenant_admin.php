<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $role->permissions = array_merge($role->permissions ?? [], [
            'dashboard' => 1,
        ]);

        $role->save();
    }

    public function down(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $perms = $role->permissions ?? [];
        unset($perms['dashboard']);
        $role->permissions = $perms;
        $role->save();
    }
};
