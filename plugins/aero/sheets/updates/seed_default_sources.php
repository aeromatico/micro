<?php

use Aero\Sheets\Models\Source;
use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Updates\Seeder;

/**
 * Fuentes por defecto, conservadoras y abiertas a tenants, para que el agente de
 * hojas tenga con qué trabajar. Solo crea las que no existen (nunca pisa lo que
 * el superadmin haya editado) y solo si el modelo está instalado. El superadmin
 * puede quitar campos o apagarlas en «Fuentes y campos».
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
            \Aero\Shop\Models\Product::class => ['Productos de la tienda', [
                $this->f('name', 'Nombre'),
                $this->f('description', 'Descripción'),
                $this->f('sku', 'SKU'),
                $this->f('base_price', 'Precio', 'decimal'),
                $this->f('compare_at_price', 'Precio anterior', 'decimal'),
                $this->f('status', 'Estado (draft/active/archived)'),
                $this->f('is_featured', 'Destacado', 'bool'),
                $this->f('slug', 'Identificador (slug)', 'string', false, true),
                // El stock se ajusta desde Inventario (deja movimientos): aquí solo se exporta.
                $this->f('stock_quantity', 'Stock', 'int', false, true),
            ]],
            \Aero\Shop\Models\Collection::class => ['Colecciones de la tienda', [
                $this->f('name', 'Nombre'),
                $this->f('description', 'Descripción'),
                $this->f('is_active', 'Activa', 'bool'),
                $this->f('slug', 'Identificador (slug)', 'string', false, true),
            ]],
            \Aero\Crm\Models\Contact::class => ['Contactos del CRM', [
                $this->f('first_name', 'Nombre'),
                $this->f('last_name', 'Apellido'),
                $this->f('email', 'Correo'),
                $this->f('phone', 'Teléfono'),
                $this->f('source', 'Origen'),
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
