<?php

use Aero\Sheets\Models\Source;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;

/**
 * Fuente «Pacientes del consultorio» (Aero.Medical) para importar y exportar con Google Sheets.
 * Solo datos administrativos: el contenido clínico (alergias, antecedentes, notas, peso, tipo
 * de sangre) NO se ofrece, así no sale a Google ni se carga sin revisión. Los datos de contacto
 * (persona o tutor) son solo de importación: crean o reutilizan el cliente de Office.
 * Solo crea la fuente si no existe (nunca pisa lo que el superadmin haya editado).
 */
return new class extends Seeder
{
    protected function f(string $key, string $label, string $type = 'string', bool $import = true, bool $export = true): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'import' => $import, 'export' => $export];
    }

    public function run(): void
    {
        $class = \Aero\Medical\Models\Patient::class;

        if (!Schema::hasTable('aero_sheets_sources') || !class_exists($class) || Source::where('model_class', $class)->exists()) {
            return;
        }

        Source::create([
            'model_class'   => $class,
            'label'         => 'Pacientes del consultorio',
            'tenant_access' => true,
            'is_enabled'    => true,
            'fields'        => [
                $this->f('name', 'Nombre completo (o nombre del animal)'),
                $this->f('document', 'Documento (CI/NIT)'),
                $this->f('birth_date', 'Fecha de nacimiento', 'date'),
                $this->f('approx_age', 'Edad aproximada'),
                $this->f('sex', 'Sexo (male/female/unknown)'),
                $this->f('species', 'Especie, solo animales (dog/cat/bird/rabbit/rodent/reptile/horse/cattle/sheep_goat/pig/other)'),
                $this->f('breed', 'Raza, solo animales'),
                $this->f('microchip', 'Microchip, solo animales'),
                $this->f('is_active', 'Activo', 'bool'),
                $this->f('contact_name', 'Tutor: nombre (animales)', 'string', true, false),
                $this->f('contact_phone', 'Teléfono de contacto', 'string', true, false),
                $this->f('contact_email', 'Correo de contacto', 'string', true, false),
            ],
        ]);
    }
};
