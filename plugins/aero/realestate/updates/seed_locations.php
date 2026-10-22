<?php

use October\Rain\Database\Updates\Migration;

/** Catálogo global de Bolivia: 9 departamentos con su capital. Los tenants agregan zonas y barrios. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aero_realestate_locations')) {
            return;
        }

        $map = [
            'La Paz' => 'La Paz', 'Santa Cruz' => 'Santa Cruz de la Sierra', 'Cochabamba' => 'Cochabamba',
            'Oruro' => 'Oruro', 'Potosí' => 'Potosí', 'Chuquisaca' => 'Sucre', 'Tarija' => 'Tarija',
            'Beni' => 'Trinidad', 'Pando' => 'Cobija',
        ];

        foreach ($map as $department => $capital) {
            $dep = \Aero\Realestate\Models\Location::firstOrCreate(
                ['tenant_id' => null, 'parent_id' => null, 'type' => 'department', 'name' => $department]
            );
            \Aero\Realestate\Models\Location::firstOrCreate(
                ['tenant_id' => null, 'parent_id' => $dep->id, 'type' => 'city', 'name' => $capital]
            );
        }
    }

    public function down(): void
    {
        // Datos semilla globales: no se borran.
    }
};
