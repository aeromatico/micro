<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Costo de envío de la tienda (productos que requieren envío). 0 = envío gratis. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->decimal('shipping_fee', 12, 2)->default(0)->comment('Costo de envío de la tienda; 0 = gratis')->after('branches_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->dropColumn('shipping_fee');
        });
    }
};
