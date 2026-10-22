<?php

use October\Rain\Database\Updates\Migration;

/** Características comunes, globales (tenant_id null); cada inmobiliaria agrega las suyas. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_realestate_features')) {
            return;
        }

        $features = [
            'exterior'  => ['Piscina', 'Jardín', 'Terraza', 'Balcón', 'Parrillero', 'Patio'],
            'interior'  => ['Cocina equipada', 'Closets empotrados', 'Chimenea', 'Aire acondicionado', 'Calefacción', 'Amoblado'],
            'servicios' => ['Agua potable', 'Gas domiciliario', 'Internet', 'Lavandería', 'Depósito'],
            'seguridad' => ['Seguridad 24 horas', 'Cámaras de vigilancia', 'Condominio cerrado', 'Alarma'],
            'edificio'  => ['Ascensor', 'Gimnasio', 'Salón de eventos', 'Área de juegos', 'Estacionamiento de visitas'],
        ];

        foreach ($features as $category => $names) {
            foreach ($names as $name) {
                \Aero\Realestate\Models\Feature::firstOrCreate(['tenant_id' => null, 'name' => $name], ['category' => $category]);
            }
        }
    }

    public function down(): void
    {
        // Datos semilla globales: no se borran.
    }
};
