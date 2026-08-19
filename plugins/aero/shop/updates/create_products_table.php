<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained('aero_shop_collections')->nullOnDelete();
            $table->string('type')->default('physical')->comment('physical|digital');
            $table->string('name');
            $table->string('slug');
            $table->longText('description')->nullable();
            $table->string('sku')->nullable();
            $table->boolean('has_variants')->default(false);
            $table->decimal('base_price', 14, 4)->default(0);
            $table->decimal('compare_at_price', 14, 4)->nullable();
            $table->decimal('cost_price', 14, 4)->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->boolean('requires_shipping')->default(true);
            $table->boolean('track_inventory')->default(true);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('allow_backorder')->default(false);
            $table->string('status')->default('draft')->comment('draft|active|archived');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'collection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_products');
    }
};
