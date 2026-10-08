<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** Incluir el servicio en el megamenú del sitio (además de estar público). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->boolean('in_megamenu')->default(false)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->dropColumn('in_megamenu');
        });
    }
};
