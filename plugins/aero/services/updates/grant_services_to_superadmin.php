<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if ($role = \Backend\Models\UserRole::where('code', 'superadmin')->first()) {
            $role->permissions = array_merge($role->permissions ?? [], ['aero.services.manage' => 1]);
            $role->save();
        }
    }

    public function down(): void
    {
        if ($role = \Backend\Models\UserRole::where('code', 'superadmin')->first()) {
            $perms = $role->permissions ?? [];
            unset($perms['aero.services.manage']);
            $role->permissions = $perms;
            $role->save();
        }
    }
};
