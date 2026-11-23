<?php

use October\Rain\Database\Updates\Migration;

/** El tenant gestiona sus anuncios en Redes sociales → Anuncios. Guardado por class_exists. */
return new class extends Migration
{
    protected array $permissions = ['aero.social.ads'];

    public function up(): void
    {
        if (!class_exists(\Aero\Social\Plugin::class)) {
            return;
        }

        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $role->permissions = array_merge($role->permissions ?? [], array_fill_keys($this->permissions, 1));
        $role->save();
    }

    public function down(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $perms = $role->permissions ?? [];
        foreach ($this->permissions as $permission) {
            unset($perms[$permission]);
        }
        $role->permissions = $perms;
        $role->save();
    }
};
