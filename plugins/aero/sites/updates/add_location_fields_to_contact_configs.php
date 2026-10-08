<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_contact_configs', function (Blueprint $table) {
            $table->boolean('no_physical_address')->default(false);
            $table->string('city', 120)->nullable();
            $table->string('region', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_contact_configs', function (Blueprint $table) {
            $table->dropColumn(['no_physical_address', 'city', 'region']);
        });
    }
};
