<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->foreignId('base_currency_id')->nullable()->constrained('aero_shop_currencies')->nullOnDelete();
            $table->boolean('inventory_tracking_enabled')->default(true);
            $table->boolean('guest_checkout_enabled')->default(true);
            $table->string('order_number_prefix')->nullable();
            $table->unsignedInteger('order_number_sequence')->default(0);
            $table->unsignedInteger('low_stock_threshold')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_settings');
    }
};
