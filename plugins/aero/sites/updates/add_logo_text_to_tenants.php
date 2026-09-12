<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->string('logo_text')->nullable()->after('primary_color');
            $table->string('logo_text_font')->nullable()->after('logo_text');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_tenants', function (Blueprint $table) {
            $table->dropColumn(['logo_text', 'logo_text_font']);
        });
    }
};
