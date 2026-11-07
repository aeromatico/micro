<?php

use Aero\Notify\Classes\DefaultTemplates;
use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Migration;

/**
 * Aero.Clinica: recordatorio de vacuna (clinica.vaccine.due) y Aero.Office: correo de cuenta creada al reservar
 * (office.account.created), con sus reglas y plantillas por defecto. Idempotente: nunca pisa reglas ni plantillas existentes.
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
