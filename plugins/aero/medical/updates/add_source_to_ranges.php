<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Origen de cada rango: los de referencia cargados por botón son un borrador hasta que el negocio los edita. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_medical_ranges', function (Blueprint $t) {
            if (!Schema::hasColumn('aero_medical_ranges', 'source')) {
                $t->string('source', 12)->default('custom')->comment('custom | reference (borrador de referencia, sin revisión médica)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('aero_medical_ranges', function (Blueprint $t) {
            $t->dropColumn('source');
        });
    }
};
