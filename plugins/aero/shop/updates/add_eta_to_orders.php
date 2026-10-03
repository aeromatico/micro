<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable();  // cocina aceptó el pedido (empieza el reloj)
            $table->timestamp('promised_at')->nullable();  // hora prometida al cliente (listo / llegada)
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_orders', function (Blueprint $table) {
            $table->dropColumn(['accepted_at', 'promised_at']);
        });
    }
};
