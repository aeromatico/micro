<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_pages', function (Blueprint $table) {
            $table->string('content_mode', 20)->default('puck')->after('puck_data');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_pages', function (Blueprint $table) {
            $table->dropColumn('content_mode');
        });
    }
};
