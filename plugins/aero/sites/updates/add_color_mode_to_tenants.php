<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            // Modo de color por defecto del sitio público: auto (según el dispositivo), dark o light.
            $table->string('color_mode', 10)->default('auto');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->dropColumn('color_mode');
        });
    }
};
