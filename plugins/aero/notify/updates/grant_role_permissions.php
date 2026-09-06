<?php

use October\Rain\Database\Updates\Migration;

/**
 * Todo el gateway queda reservado a superadmin, sin excepción.
 *
 * El catálogo de eventos es genérico para toda la plataforma (no por tenant),
 * y todavía no existe el motor de entrega ni la resolución de audiencias por
 * tenant (fase 2). Hasta que eso exista, ningún permiso de Notify se concede a
 * tenant_admin: no hay nada que un dueño de tenant deba configurar aquí.
 */
return new class extends Migration
{
    protected array $superadminPermissions = [
        'aero.notify.view_events'         => 1,
        'aero.notify.manage_events'       => 1,
        'aero.notify.manage_global_rules' => 1,
        'aero.notify.manage_rules'        => 1,
        'aero.notify.manage_templates'    => 1,
        'aero.notify.manage_channels'     => 1,
        'aero.notify.view_deliveries'     => 1,
        'aero.notify.resend'              => 1,
        'aero.notify.send_test'           => 1,
    ];

    public function up(): void
    {
        $this->applyTo('superadmin', $this->superadminPermissions);
    }

    public function down(): void
    {
        $all = array_keys($this->superadminPermissions);

        foreach (['superadmin'] as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();

            if (!$role) {
                continue;
            }

            $permissions = $role->permissions ?? [];

            foreach ($all as $key) {
                unset($permissions[$key]);
            }

            $role->permissions = $permissions;
            $role->save();
        }
    }

    protected function applyTo(string $roleCode, array $permissions): void
    {
        $role = \Backend\Models\UserRole::where('code', $roleCode)->first();

        if (!$role) {
            return;
        }

        // Merge y no reemplazo: el rol puede tener permisos de otros plugins.
        $role->permissions = array_merge($role->permissions ?? [], $permissions);
        $role->save();
    }
};
