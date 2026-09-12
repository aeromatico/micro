<?php

use October\Rain\Database\Updates\Migration;

/**
 * Switch "En el pipeline" en Lead: al convertir el lead en contacto + deal,
 * el deal creado hereda este valor, de modo que un lead que no debe ir al
 * tablero no aparezca en el kanban. Por defecto true (visible).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_leads', function ($table) {
            $table->boolean('in_pipeline')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_leads', function ($table) {
            $table->dropColumn('in_pipeline');
        });
    }
};
