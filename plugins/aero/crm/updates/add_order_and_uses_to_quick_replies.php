<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_quick_replies', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(10)->after('body');
            $table->unsignedInteger('uses_count')->default(0)->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_quick_replies', function (Blueprint $table) {
            $table->dropColumn(['sort_order', 'uses_count']);
        });
    }
};
