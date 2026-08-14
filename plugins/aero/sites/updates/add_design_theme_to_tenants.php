<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('design_theme_id')->nullable()->after('primary_color');
            $table->json('theme_overrides')->nullable()->after('design_theme_id');

            $table->foreign('design_theme_id')->references('id')->on('aero_sites_design_themes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->dropForeign(['design_theme_id']);
            $table->dropColumn(['design_theme_id', 'theme_overrides']);
        });
    }
};
