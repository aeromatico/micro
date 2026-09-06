<?php

use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Seeder;

/**
 * Siembra el catálogo inicial de eventos y sus reglas globales por defecto.
 * La lógica vive en EventSeeder para que esta migración y el comando
 * notify:seed-events no sean dos copias que se desincronizan con el tiempo.
 */
return new class extends Seeder
{
    public function run(): void
    {
        (new EventSeeder)->run();
    }
};
