<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Número del día para restaurantes (1, 2, 3… y vuelve a 1 cada día): se dice en voz alta en el salón.
 * Convive con el número de pedido, que no se reinicia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_daily_counters', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->date('day');
            $table->unsignedInteger('last')->default(0);
            $table->primary(['tenant_id', 'day']);
        });

        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->unsignedInteger('daily_number')->nullable()->after('order_number');
            $table->date('daily_date')->nullable()->after('daily_number');
            $table->unique(['tenant_id', 'daily_date', 'daily_number'], 'aero_shop_orders_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->dropUnique('aero_shop_orders_daily_unique');
            $table->dropColumn(['daily_number', 'daily_date']);
        });
        Schema::dropIfExists('aero_shop_daily_counters');
    }
};
