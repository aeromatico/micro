<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_pages', function (Blueprint $table) {
            $table->boolean('is_placeholder')->default(false)->after('content_mode');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_pages', function (Blueprint $table) {
            $table->dropColumn('is_placeholder');
        });
    }
};
