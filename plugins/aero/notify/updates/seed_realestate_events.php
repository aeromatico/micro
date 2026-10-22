<?php

use Aero\Notify\Classes\DefaultTemplates;
use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Migration;

/**
 * Inmobiliaria (Aero.Realestate): avisos de vencimiento de anticréticos y documentos
 * pendientes, con sus reglas y plantillas por defecto. Idempotente.
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
