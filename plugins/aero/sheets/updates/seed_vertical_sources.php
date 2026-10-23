<?php

use Aero\Sheets\Models\Source;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;

/**
 * Fuentes de los rubros (socios de gimnasio, inmuebles, propietarios y clientes inmobiliarios)
 * para importar/exportar con Google Sheets. Solo campos propios del registro: las relaciones
 * (dueño, agente, ubicación, membresías) se enlazan desde sus pantallas. Solo crea las que no
 * existen y solo si el modelo está instalado.
 */
return new class extends Seeder
{
    protected function f(string $key, string $label, string $type = 'string', bool $import = true, bool $export = true): array
    {
        return ['key' => $key, 'label' => $label, 'type' => $type, 'import' => $import, 'export' => $export];
    }

    public function run(): void
    {
        if (!Schema::hasTable('aero_sheets_sources')) {
            return;
        }

        $sources = [
            \Aero\Gym\Models\Member::class => ['Socios del gimnasio', [
                $this->f('name', 'Nombre completo'),
                $this->f('phone', 'Teléfono'),
                $this->f('email', 'Correo'),
                $this->f('document', 'Documento (CI)'),
                $this->f('birthdate', 'Fecha de nacimiento', 'date'),
                $this->f('card_number', 'Número de tarjeta'),
                $this->f('status', 'Estado (active/suspended/inactive)'),
                $this->f('notes', 'Notas'),
            ]],
            \Aero\Realestate\Models\Property::class => ['Inmuebles', [
                $this->f('code', 'Código'),
                $this->f('title', 'Título'),
                $this->f('type', 'Tipo (house/apartment/land/office/shop/other)'),
                $this->f('status', 'Estado (available/reserved/sold/rented/anticretico/inactive)'),
                $this->f('description', 'Descripción'),
                $this->f('address', 'Dirección'),
                $this->f('department', 'Departamento'),
                $this->f('city', 'Ciudad'),
                $this->f('zone', 'Zona'),
                $this->f('neighborhood', 'Barrio'),
                $this->f('land_area', 'Superficie del terreno (m²)', 'decimal'),
                $this->f('built_area', 'Superficie construida (m²)', 'decimal'),
                $this->f('bedrooms', 'Dormitorios', 'int'),
                $this->f('bathrooms', 'Baños', 'int'),
                $this->f('garages', 'Garajes', 'int'),
                $this->f('floors', 'Pisos', 'int'),
                $this->f('age_years', 'Antigüedad (años)', 'int'),
                $this->f('is_featured', 'Destacado', 'bool'),
                // Publicar es una decisión posterior a revisar la ficha: aquí solo se exporta.
                $this->f('is_published', 'Publicado', 'bool', false, true),
            ]],
            \Aero\Realestate\Models\Owner::class => ['Propietarios (inmobiliaria)', [
                $this->f('name', 'Nombre'),
                $this->f('phone', 'Teléfono'),
                $this->f('email', 'Correo'),
                $this->f('document', 'Documento'),
                $this->f('address', 'Dirección'),
                $this->f('notes', 'Notas'),
            ]],
            \Aero\Realestate\Models\Client::class => ['Clientes (inmobiliaria)', [
                $this->f('name', 'Nombre'),
                $this->f('phone', 'Teléfono'),
                $this->f('email', 'Correo'),
                $this->f('document', 'Documento'),
                $this->f('source', 'Origen'),
                $this->f('budget_min', 'Presupuesto mínimo', 'decimal'),
                $this->f('budget_max', 'Presupuesto máximo', 'decimal'),
                $this->f('currency', 'Moneda'),
                $this->f('notes', 'Notas'),
            ]],
        ];

        foreach ($sources as $class => [$label, $fields]) {
            if (!class_exists($class) || Source::where('model_class', $class)->exists()) {
                continue;
            }

            Source::create(['model_class' => $class, 'label' => $label, 'fields' => $fields, 'tenant_access' => true, 'is_enabled' => true]);
        }
    }
};
