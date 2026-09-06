<?php

use October\Rain\Database\Updates\Migration;

/**
 * Reparte los permisos del gateway entre los dos roles del proyecto.
 *
 * El catálogo de eventos y las reglas de la plataforma quedan fuera de
 * tenant_admin a propósito: un dueño de tenant configura a quién y por dónde se
 * le avisa, pero no inventa eventos ni toca los valores por defecto de los demás.
 */
return new class extends Migration
{
    /** Lo que puede hacer el dueño de un tenant. */
    protected array $tenantAdminPermissions = [
        'aero.notify.view_events'      => 1,
        'aero.notify.manage_rules'     => 1,
        'aero.notify.manage_templates' => 1,
        'aero.notify.manage_channels'  => 1,
        'aero.notify.view_deliveries'  => 1,
        'aero.notify.resend'           => 1,
        'aero.notify.send_test'        => 1,
    ];

    /** Lo anterior más el control de la plataforma. */
    protected array $superadminOnlyPermissions = [
        'aero.notify.manage_events'       => 1,
        'aero.notify.manage_global_rules' => 1,
    ];

    public function up(): void
    {
        $this->applyTo('tenant_admin', $this->tenantAdminPermissions);
        $this->applyTo('superadmin', $this->tenantAdminPermissions + $this->superadminOnlyPermissions);
    }

    public function down(): void
    {
        $all = array_keys($this->tenantAdminPermissions + $this->superadminOnlyPermissions);

        foreach (['tenant_admin', 'superadmin'] as $code) {
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
