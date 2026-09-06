<?php

use October\Rain\Database\Updates\Migration;

/**
 * "Quitar del pipeline" (botón de la tarjeta en el board) ya no borra el
 * deal: solo lo saca de la vista de kanban vía este switch, para no perder
 * el historial de actividades/valor asociado. Por defecto true (visible).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_deals', function ($table) {
            $table->boolean('in_pipeline')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_deals', function ($table) {
            $table->dropColumn('in_pipeline');
        });
    }
};
