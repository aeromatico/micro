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
            'general.backend'              => 1,
            'aero.sites.manage_pages'      => 1,
            'aero.sites.manage_seo'        => 1,
            'aero.sites.manage_contact'    => 1,
            'aero.sites.view_submissions'  => 1,
            'aero.sites.manage_api_tokens' => 1,
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
        unset($perms['general.backend']);
        $role->permissions = $perms;
        $role->save();
    }
};
