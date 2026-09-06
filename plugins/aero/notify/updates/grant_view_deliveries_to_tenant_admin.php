<?php

use October\Rain\Database\Updates\Migration;

/**
 * 1.0.2 revocó todo Notify de tenant_admin porque no existía motor de
 * entrega. Ahora que Notify::fire() reparte de verdad (ver classes/Notify.php),
 * un tenant_admin puede consultar qué se le entregó y por qué — sigue sin
 * poder tocar catálogo/reglas/plantillas, eso sigue siendo de plataforma.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->apply(['aero.notify.view_deliveries' => 1]);
    }

    public function down(): void
    {
        $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();

        if (!$role) {
            return;
        }

        $permissions = $role->permissions ?? [];
        unset($permissions['aero.notify.view_deliveries']);
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
