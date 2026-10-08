<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Pedido con la ubicación compartida por el cliente en el chat: dirección y
 * ciudad pasan a ser opcionales cuando hay coordenadas (OrderService lo valida).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_addresses', function (Blueprint $table) {
            $table->string('address_line1')->nullable()->change();
            $table->string('city')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('aero_shop_addresses')->whereNull('address_line1')->update(['address_line1' => '']);
        DB::table('aero_shop_addresses')->whereNull('city')->update(['city' => '']);

        Schema::table('aero_shop_addresses', function (Blueprint $table) {
            $table->string('address_line1')->nullable(false)->change();
            $table->string('city')->nullable(false)->change();
        });
    }
};
