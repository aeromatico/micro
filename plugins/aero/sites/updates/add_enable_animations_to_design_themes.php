<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_design_themes', function (Blueprint $table) {
            $table->boolean('enable_animations')->default(true)->after('radius');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_design_themes', function (Blueprint $table) {
            $table->dropColumn('enable_animations');
        });
    }
};
