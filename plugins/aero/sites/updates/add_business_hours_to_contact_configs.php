<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_contact_configs', function (Blueprint $table) {
            // [{day, open, close, closed}] — un día puede repetirse (turno partido).
            $table->json('business_hours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_contact_configs', function (Blueprint $table) {
            $table->dropColumn('business_hours');
        });
    }
};
