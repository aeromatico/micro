<?php

use October\Rain\Database\Updates\Migration;

/**
 * Quita los cuestionarios: no son parte del flujo del médico en esta clínica. Solo borra esas dos tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('aero_clinica_questionnaire_answers');
        Schema::dropIfExists('aero_clinica_questionnaires');
    }

    public function down(): void
    {
        // Sin retroceso: se quitaron a propósito.
    }
};
