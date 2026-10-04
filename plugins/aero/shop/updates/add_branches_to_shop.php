<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->boolean('branches_enabled')->default(false);
            $table->json('branches')->nullable();
        });

        // Snapshot: el nombre queda aunque la sucursal se renombre o desactive después.
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->string('branch_id', 40)->nullable();
            $table->string('branch_name', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->dropColumn(['branch_id', 'branch_name']);
        });

        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->dropColumn(['branches_enabled', 'branches']);
        });
    }
};
