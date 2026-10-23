<?php

use October\Rain\Database\Updates\Migration;

/** Roles de panel de la iglesia. Idempotente: no pisa los permisos si el rol ya existe. */
return new class extends Migration
{
    protected array $roles = [
        'church_pastor'    => ['Iglesia: pastor o dirección', ['aero.church.pastor']],
        'church_secretary' => ['Iglesia: secretaría', ['aero.church.secretary']],
        'church_treasury'  => ['Iglesia: tesorería', ['aero.church.treasury']],
        'church_leader'    => ['Iglesia: líder de ministerio', ['aero.church.leader']],
    ];

    public function up(): void
    {
        if (!class_exists(\Backend\Models\UserRole::class) || !\Schema::hasTable('backend_user_roles')) {
            return;
        }

        foreach ($this->roles as $code => [$name, $perms]) {
            \Backend\Models\UserRole::firstOrCreate(['code' => $code], [
                'name'        => $name,
                'description' => $name . '.',
                'permissions' => array_fill_keys(array_merge(['general.backend', 'dashboard'], $perms), 1),
            ]);
        }
    }

    public function down(): void
    {
        if (!\Schema::hasTable('backend_user_roles')) {
            return;
        }
        \Backend\Models\UserRole::whereIn('code', array_keys($this->roles))->delete();
    }
};
