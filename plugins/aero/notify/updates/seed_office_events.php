<?php

use Aero\Notify\Classes\DefaultTemplates;
use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Migration;

/**
 * Aero.Office: eventos de reservas (recibida, confirmada, rechazada, cancelada,
 * reprogramada, recordatorio y avisos al negocio) con sus reglas y plantillas
 * por defecto. Idempotente: el seeder nunca pisa reglas ni plantillas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new EventSeeder)->run();
        DefaultTemplates::seedMissing();
    }

    public function down(): void
    {
    }
};
