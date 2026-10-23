<?php

use October\Rain\Database\Updates\Migration;

/** Sin `general.backend` los roles de la iglesia no podían entrar al panel (403 en todo). Suma lo básico sin quitar nada. */
return new class extends Migration
{
    protected array $codes = ['church_pastor', 'church_secretary', 'church_treasury', 'church_leader'];

    public function up(): void
    {
        if (!\Schema::hasTable('backend_user_roles')) {
            return;
        }

        foreach (\Backend\Models\UserRole::whereIn('code', $this->codes)->get() as $role) {
            $role->permissions = array_merge($role->permissions ?? [], ['general.backend' => 1, 'dashboard' => 1]);
            $role->save();
        }
    }

    public function down(): void
    {
        // Quitar el acceso básico dejaría los roles inutilizables; no se revierte.
    }
};
