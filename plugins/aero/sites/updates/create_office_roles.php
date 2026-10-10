<?php

use October\Rain\Database\Updates\Migration;

/**
 * Roles de panel para el personal de un negocio de Aero.Office:
 *  - office_admin:        administra operaciones (sin configuración/portal del propietario)
 *  - office_receptionist: clientes, citas y agenda
 *  - office_professional: consulta sus propias reservas
 * El propietario es el tenant_admin. Idempotente; solo si Office está instalado.
 */
return new class extends Migration
{
    protected array $roles = [
        'office_admin' => [
            'name' => 'Oficina: Administrador',
            'description' => 'Gestiona sucursales, servicios, profesionales, clientes y reservas del negocio.',
            'permissions' => ['general.backend' => 1, 'dashboard' => 1, 'aero.office.use' => 1, 'aero.office.reception' => 1, 'aero.office.professional' => 1],
        ],
        'office_receptionist' => [
            'name' => 'Oficina: Recepcionista',
            'description' => 'Administra clientes, citas y agenda.',
            'permissions' => ['general.backend' => 1, 'dashboard' => 1, 'aero.office.reception' => 1, 'aero.office.professional' => 1],
        ],
        'office_professional' => [
            'name' => 'Oficina: Profesional',
            'description' => 'Consulta sus reservas y atenciones.',
            'permissions' => ['general.backend' => 1, 'dashboard' => 1, 'aero.office.professional' => 1],
        ],
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\Office\Plugin::class)) {
            return;
        }

        foreach ($this->roles as $code => $def) {
            \Backend\Models\UserRole::firstOrCreate(
                ['code' => $code],
                ['name' => $def['name'], 'description' => $def['description'], 'permissions' => $def['permissions']]
            );
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->roles) as $code) {
            $role = \Backend\Models\UserRole::where('code', $code)->first();
            if ($role && !\Backend\Models\User::where('role_id', $role->id)->exists()) {
                $role->delete();
            }
        }
    }
};
