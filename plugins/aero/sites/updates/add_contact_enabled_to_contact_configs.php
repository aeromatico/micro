<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_contact_configs', function (Blueprint $table) {
            // Encendido por defecto: los sitios existentes siguen mostrando su contacto.
            $table->boolean('contact_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_contact_configs', function (Blueprint $table) {
            $table->dropColumn('contact_enabled');
        });
    }
};
