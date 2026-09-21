<?php

use October\Rain\Database\Updates\Migration;

/**
 * Rol "Tenant Admin PRO": mismos permisos que tenant_admin (el menú se ve
 * igual para ambos; lo PRO lo decide Aero\Sites\Classes\ProFeatures según las
 * pantallas marcadas por el superadmin). Los tenants con plan pro existentes
 * pasan al rol nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $base = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();

        $pro = \Backend\Models\UserRole::firstOrCreate(
            ['code' => 'tenant_admin_pro'],
            [
                'name'        => 'Tenant Admin PRO',
                'description' => 'Administrador de un tenant con plan PRO — accede a todas las pantallas, incluidas las marcadas como PRO.',
                'permissions' => $base->permissions ?? [],
            ]
        );

        foreach (\Aero\Sites\Models\Tenant::where('plan', 'pro')->get() as $tenant) {
            $tenant->syncAdminRoles();
        }
    }

    public function down(): void
    {
        $regular = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
        $pro = \Backend\Models\UserRole::where('code', 'tenant_admin_pro')->first();
        if ($pro) {
            if ($regular) {
                \Backend\Models\User::where('role_id', $pro->id)->update(['role_id' => $regular->id]);
            }
            $pro->delete();
        }
    }
};
