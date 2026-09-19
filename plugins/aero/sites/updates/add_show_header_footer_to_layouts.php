<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_layouts', function (Blueprint $table) {
            $table->boolean('show_header')->default(true)->after('mode');
            $table->boolean('show_footer')->default(true)->after('show_header');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_layouts', function (Blueprint $table) {
            $table->dropColumn(['show_header', 'show_footer']);
        });
    }
};
