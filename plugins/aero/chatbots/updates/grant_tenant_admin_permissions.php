<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $this->applyToRole('tenant_admin', ['aero.chatbots.manage' => 1]);
        $this->applyToRole('superadmin', ['aero.chatbots.manage' => 1, 'aero.chatbots.superadmin' => 1]);
    }

    public function down(): void
    {
        $this->removeFromRole('tenant_admin', ['aero.chatbots.manage']);
        $this->removeFromRole('superadmin', ['aero.chatbots.manage', 'aero.chatbots.superadmin']);
    }

    protected function applyToRole(string $code, array $permissions): void
    {
        $role = \Backend\Models\UserRole::where('code', $code)->first();
        if (!$role) {
            return;
        }

        $role->permissions = array_merge($role->permissions ?? [], $permissions);
        $role->save();
    }

    protected function removeFromRole(string $code, array $keys): void
    {
        $role = \Backend\Models\UserRole::where('code', $code)->first();
        if (!$role) {
            return;
        }

        $perms = $role->permissions ?? [];
        foreach ($keys as $key) {
            unset($perms[$key]);
        }
        $role->permissions = $perms;
        $role->save();
    }
};
