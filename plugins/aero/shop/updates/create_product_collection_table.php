<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_product_collection', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('aero_shop_products')->cascadeOnDelete();
            $table->foreignId('collection_id')->constrained('aero_shop_collections')->cascadeOnDelete();

            $table->unique(['product_id', 'collection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_product_collection');
    }
};
