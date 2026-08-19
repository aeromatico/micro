<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_currencies', function (Blueprint $table) {
            $table->string('code', 5)->comment('ISO 4217 o símbolo cripto, ej. BOB, USD, USDT')->change();
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_currencies', function (Blueprint $table) {
            $table->string('code', 3)->change();
        });
    }
};
