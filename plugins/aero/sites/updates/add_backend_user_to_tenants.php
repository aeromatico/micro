<?php

use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_sites_tenants', function ($table) {
            $table->unsignedBigInteger('backend_user_id')->nullable()->after('site_id');
            $table->index('backend_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('aero_sites_tenants', function ($table) {
            $table->dropIndex(['backend_user_id']);
            $table->dropColumn('backend_user_id');
        });
    }
};
