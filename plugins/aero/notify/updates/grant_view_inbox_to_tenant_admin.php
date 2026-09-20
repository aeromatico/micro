<?php

use October\Rain\Database\Updates\Migration;

/** La bandeja in-app es de cada usuario: se concede a tenant_admin para que la vea. */
return new class extends Migration
{
    public function up(): void
    {
        $this->apply(['aero.notify.view_inbox' => 1]);
    }

    public function down(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();

        if (!$role) {
            return;
        }

        $permissions = $role->permissions ?? [];
        unset($permissions['aero.notify.view_inbox']);
        $role->permissions = $permissions;
        $role->save();
    }

    protected function apply(array $permissions): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();

        if (!$role) {
            return;
        }

        $role->permissions = array_merge($role->permissions ?? [], $permissions);
        $role->save();
    }
};
