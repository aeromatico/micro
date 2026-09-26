<?php

use Aero\Connector\Models\Connector;
use October\Rain\Database\Updates\Seeder;

/**
 * Crea el único Connector que el proxy necesita: credencial x-api-key
 * compartida por las 155 rutas (ver Aero\Hub\Classes\Connector\YepApiDriver).
 *
 * A propósito NO trae la API key en claro: este archivo se versiona en git, y
 * escribirla acá la dejaría en el historial para siempre aunque se borre
 * después. Tras migrar, pega la key real desde el backend:
 * Aero.Connector → Connectors → YepAPI → campo "API Key" (se cifra al guardar).
 */
return new class extends Seeder
{
    public function run(): void
    {
        Connector::firstOrCreate(
            ['type' => 'yepapi'],
            [
                'name'          => 'YepAPI',
                'provider_hint' => 'yepapi',
                'base_url'      => 'https://api.yepapi.com',
                'is_enabled'    => true,
            ]
        );
    }
};
