<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * `kind` de los archivos medía 12 caracteres y «grooming_before» tiene 15: MySQL en modo estricto
 * rechazaba las fotos de peluquería (SQLite, que usan las pruebas, no lo hace).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_medical_files', function (Blueprint $t) {
            $t->string('kind', 24)->default('other')->change();
        });
    }

    public function down(): void
    {
        // Sin acción: volver a 12 truncaría o rechazaría los tipos de foto existentes.
    }
};
