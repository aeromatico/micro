<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_settings', function (Blueprint $table) {
            $table->boolean('tickets_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_settings', function (Blueprint $table) {
            $table->dropColumn('tickets_enabled');
        });
    }
};
