<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** Página completa de la oferta (HTML): características, casos de uso, FAQ y documentación. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->longText('code')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
