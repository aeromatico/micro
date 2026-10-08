<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_products', function (Blueprint $table) {
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->unsignedSmallInteger('min_quantity')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_products', function (Blueprint $table) {
            $table->dropColumn(['prep_minutes', 'min_quantity']);
        });
    }
};
