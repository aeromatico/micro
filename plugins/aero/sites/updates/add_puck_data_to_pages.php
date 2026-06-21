<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_pages', function (Blueprint $table) {
            $table->json('puck_data')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_pages', function (Blueprint $table) {
            $table->dropColumn('puck_data');
        });
    }
};
