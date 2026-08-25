<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_contacts', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('phone');
        });

        Schema::table('aero_crm_companies', function (Blueprint $table) {
            $table->json('social_links')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_contacts', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });

        Schema::table('aero_crm_companies', function (Blueprint $table) {
            $table->dropColumn('social_links');
        });
    }
};
