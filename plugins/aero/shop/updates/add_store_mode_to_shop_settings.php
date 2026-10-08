<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->string('store_mode', 20)->default('standard');
            $table->string('whatsapp_mode', 20)->default('api');
            $table->string('whatsapp_number', 30)->nullable();
            $table->unsignedBigInteger('whatsapp_account_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aero_shop_settings', function (Blueprint $table) {
            $table->dropColumn(['store_mode', 'whatsapp_mode', 'whatsapp_number', 'whatsapp_account_id']);
        });
    }
};
