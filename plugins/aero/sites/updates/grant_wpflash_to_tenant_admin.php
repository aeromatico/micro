<?php

use October\Rain\Database\Updates\Migration;

/**
 * Sin esto, la pantalla "Motor del sitio" de Aero.WpFlash queda invisible
 * para el tenant_admin (los permisos existen pero no vienen otorgados por
 * defecto a ese rol — ver el resto de grant_*_to_tenant_admin.php en este
 * mismo directorio). Guardado por class_exists porque Sites no depende de
 * WpFlash.
 */
return new class extends Migration
{
    protected array $permissions = [
        'aero.wpflash.manage_sites',
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\WpFlash\Models\SiteInstance::class)) {
            return;
        }

        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        if (!$role) {
            return;
        }

        $granted = array_fill_keys($this->permissions, 1);
        $role->permissions = array_merge($role->permissions ?? [], $granted);
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
