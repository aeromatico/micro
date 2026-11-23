<?php

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Límites numéricos por plan (los declara cada plugin; vacío = sin límite). */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('aero_sites_plans', 'limits')) {
            Schema::table('aero_sites_plans', function (Blueprint $table) {
                $table->text('limits')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('aero_sites_plans', 'limits')) {
            Schema::table('aero_sites_plans', function (Blueprint $table) {
                $table->dropColumn('limits');
            });
        }
    }
};
