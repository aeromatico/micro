<?php

use October\Rain\Database\Updates\Migration;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_layouts', function (Blueprint $table) {
            $table->longText('extra_head_html')->nullable()->after('footer_html');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_layouts', function (Blueprint $table) {
            $table->dropColumn('extra_head_html');
        });
    }
};
