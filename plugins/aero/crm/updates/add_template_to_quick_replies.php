<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_quick_replies', function (Blueprint $table) {
            $table->string('template_name', 100)->nullable()->after('body');
            $table->string('template_language', 10)->nullable()->after('template_name');
            $table->string('template_category', 20)->nullable()->after('template_language');
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_quick_replies', function (Blueprint $table) {
            $table->dropColumn(['template_name', 'template_language', 'template_category']);
        });
    }
};
