<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * La firma y el sello se guardan CIFRADOS en la base de datos (data URI), no como
 * archivos: el servidor web publica todo lo que haya bajo storage/, así que ni
 * siquiera el disco «protegido» de October es privado en este despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_medical_professionals', function (Blueprint $t) {
            if (!Schema::hasColumn('aero_medical_professionals', 'signature_image')) {
                $t->longText('signature_image')->nullable()->comment('Data URI cifrada');
            }
            if (!Schema::hasColumn('aero_medical_professionals', 'seal_image')) {
                $t->longText('seal_image')->nullable()->comment('Data URI cifrada');
            }
        });
    }

    public function down(): void
    {
        Schema::table('aero_medical_professionals', function (Blueprint $t) {
            $t->dropColumn(['signature_image', 'seal_image']);
        });
    }
};
