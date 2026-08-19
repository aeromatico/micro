<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('aero_shop_products')->cascadeOnDelete();
            $table->string('sku');
            $table->decimal('price', 14, 4);
            $table->decimal('compare_at_price', 14, 4)->nullable();
            $table->decimal('cost_price', 14, 4)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_product_variants');
    }
};
