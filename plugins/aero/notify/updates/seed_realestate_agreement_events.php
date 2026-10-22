<?php

use Aero\Notify\Classes\DefaultTemplates;
use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Migration;

/**
 * Inmobiliaria (Aero.Realestate) fase 5: vencimiento de alquileres y documentos del archivo
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
