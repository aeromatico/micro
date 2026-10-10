<?php

use October\Rain\Database\Updates\Migration;

/**
 * Los roles office_* se crearon sin `general.backend`: el personal no podía
 * entrar al panel. Se les añade el acceso al backend y al tablero.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['office_admin', 'office_receptionist', 'office_professional'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if (!$role) {
                continue;
            }
            $role->permissions = array_merge($role->permissions ?? [], ['general.backend' => 1, 'dashboard' => 1]);
            $role->save();
        }
    }

    public function down(): void
    {
        // Sin acción: quitar el acceso al backend dejaría a los usuarios fuera.
    }
};
