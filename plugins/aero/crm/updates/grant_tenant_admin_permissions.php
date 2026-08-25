<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    protected array $crmPermissions = [
        'aero.crm.manage_contacts'   => 1,
        'aero.crm.manage_companies'  => 1,
        'aero.crm.manage_leads'      => 1,
        'aero.crm.manage_deals'      => 1,
        'aero.crm.manage_activities' => 1,
        'aero.crm.manage_teams'      => 1,
        'aero.crm.manage_settings'   => 1,
    ];

    public function up(): void
    {
        foreach (['tenant_admin', 'superadmin'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }

            $role->permissions = array_merge($role->permissions ?? [], $this->crmPermissions);
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
            foreach (array_keys($this->crmPermissions) as $key) {
                unset($perms[$key]);
            }
            $role->permissions = $perms;
            $role->save();
        }
    }
};
