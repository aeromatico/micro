<?php

use October\Rain\Database\Updates\Migration;

/**
 * Roles de panel para el personal de un negocio de Aero.Clinica, encajados con
 * los de Office (que dan la agenda y las reservas):
 *  - clinica_admin:        administra pacientes y bitácora, sin contenido clínico
 *  - clinica_receptionist: datos administrativos de pacientes, sin contenido clínico
 *  - clinica_professional: contenido clínico, escribe y firma notas
 * El propietario es el tenant_admin. Idempotente; solo si Clinica está instalado.
 */
return new class extends Migration
{
    protected array $roles = [
        'clinica_admin' => [
            'name' => 'Clínica: Administrador',
            'description' => 'Administra pacientes y consulta la bitácora. No ve el contenido clínico.',
            'permissions' => ['general.backend' => 1, 'dashboard' => 1, 'aero.clinica.use' => 1, 'aero.clinica.settings' => 1, 'aero.clinica.reception' => 1, 'aero.office.use' => 1, 'aero.office.reception' => 1],
        ],
        'clinica_receptionist' => [
            'name' => 'Clínica: Recepcionista',
            'description' => 'Datos administrativos de pacientes y agenda. No ve el contenido clínico.',
            'permissions' => ['general.backend' => 1, 'dashboard' => 1, 'aero.clinica.reception' => 1, 'aero.office.reception' => 1, 'aero.office.professional' => 1],
        ],
        'clinica_professional' => [
            'name' => 'Clínica: Profesional',
            'description' => 'Atiende pacientes: contenido clínico, escribe y firma notas.',
            'permissions' => ['general.backend' => 1, 'dashboard' => 1, 'aero.clinica.professional' => 1, 'aero.office.professional' => 1],
        ],
    ];

    public function up(): void
    {
        if (!class_exists(\Aero\Clinica\Plugin::class)) {
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
