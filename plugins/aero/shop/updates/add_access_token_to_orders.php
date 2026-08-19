<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->string('access_token', 64)->nullable()->unique()->after('order_number')
                ->comment('Token público aleatorio para la página de confirmación — evita exponer/adivinar pedidos por order_number secuencial');
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->dropColumn('access_token');
        });
    }
};
