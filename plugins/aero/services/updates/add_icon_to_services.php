<?php

use Illuminate\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;
use October\Rain\Database\Updates\Migration;

/** Ícono del servicio (nombre de Lucide) para menús de otros sitios que lo consumen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->string('icon', 60)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('aero_services_services', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
