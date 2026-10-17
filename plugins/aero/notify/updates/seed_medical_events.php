<?php

use Aero\Notify\Classes\DefaultTemplates;
use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Migration;

/**
 * Aero.Medical: evento de recordatorio de vacuna (medical.vaccine.due),
 * con su regla y plantilla por defecto. Idempotente: el seeder nunca pisa reglas ni plantillas existentes.
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
