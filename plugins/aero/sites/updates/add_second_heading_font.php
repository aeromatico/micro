<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_design_themes', function (Blueprint $table) {
            $table->string('font_heading2')->nullable()->after('font_heading');
        });

        // Backfill: los temas existentes usan la misma fuente para h1 y h2-h6
        // hasta que se personalicen desde el admin.
        \Db::table('aero_sites_design_themes')->update([
            'font_heading2' => \Db::raw('font_heading'),
        ]);
    }

    public function down(): void
    {
        Schema::table('aero_sites_design_themes', function (Blueprint $table) {
            $table->dropColumn('font_heading2');
        });
    }
};
