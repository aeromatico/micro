<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('aero_shop_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('aero_shop_products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('aero_shop_product_variants')->nullOnDelete();
            $table->string('product_name_snapshot');
            $table->string('variant_label_snapshot')->nullable();
            $table->string('sku_snapshot')->nullable();
            $table->decimal('unit_price', 14, 4);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 14, 4);
            $table->string('product_type_snapshot')->default('physical');
            $table->timestamps();

            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_order_items');
    }
};
