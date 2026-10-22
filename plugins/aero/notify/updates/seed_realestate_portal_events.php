<?php

use Aero\Notify\Classes\DefaultTemplates;
use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Migration;

/**
 * Inmobiliaria (Aero.Realestate) fase 4: consultas del portal y alertas de búsquedas y cambios
 * con sus reglas y plantillas por defecto. Idempotente.
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
