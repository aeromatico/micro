<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_product_option_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('product_option_id')->constrained('aero_shop_product_options')->cascadeOnDelete();
            $table->string('value');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_product_option_values');
    }
};
