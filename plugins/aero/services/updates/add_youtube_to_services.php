<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** ID del video de YouTube del servicio (se guarda solo el ID de 11 caracteres; el formulario acepta también la URL). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_services_services', 'youtube_id')) {
            Schema::table('aero_services_services', function (Blueprint $table) {
                $table->string('youtube_id', 20)->nullable()->after('icon');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_services_services', 'youtube_id')) {
            Schema::table('aero_services_services', function (Blueprint $table) {
                $table->dropColumn('youtube_id');
            });
        }
    }
};
