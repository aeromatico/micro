<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('aero_shop_products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('aero_shop_product_variants')->cascadeOnDelete();
            $table->string('type')->comment('sale|manual_adjustment|restock|return|initial');
            $table->integer('quantity_delta');
            $table->unsignedInteger('quantity_after');
            $table->foreignId('order_id')->nullable()->constrained('aero_shop_orders')->nullOnDelete();
            $table->string('note')->nullable();
            $table->unsignedInteger('created_by_backend_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'product_id']);
            $table->index(['product_variant_id']);
            $table->index(['order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_stock_movements');
    }
};
