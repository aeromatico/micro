<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Quita los catálogos de medicamentos (propio y LINAME) y de vacunas: en Bolivia la prescripción y el esquema no se
 * limitan a una sola lista, así que la receta y el registro de dosis son de texto libre con sugerencias de lo ya usado.
 * Solo borra catálogos; las recetas y las dosis aplicadas conservan su texto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('aero_clinica_liname');
        Schema::dropIfExists('aero_clinica_medications');
        Schema::dropIfExists('aero_clinica_vaccines');

        if (Schema::hasColumn('aero_clinica_vaccinations', 'vaccine_id')) {
            Schema::table('aero_clinica_vaccinations', function (Blueprint $t) {
                $t->dropColumn('vaccine_id');
            });
        }
    }

    public function down(): void
    {
        // Sin retroceso: los catálogos se quitaron a propósito.
    }
};
