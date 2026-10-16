<?php

use Aero\Notify\Classes\DefaultTemplates;
use Aero\Notify\Classes\EventSeeder;
use October\Rain\Database\Updates\Migration;

/**
 * Tickets de soporte (Aero.Crm): confirmación y respuesta al cliente, avisos a
 * los admins del tenant (y al correo del área) y avisos a la plataforma.
 * Idempotente: el seeder nunca pisa reglas ni plantillas existentes.
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
