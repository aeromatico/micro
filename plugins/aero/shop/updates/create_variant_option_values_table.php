<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_variant_opt_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('aero_shop_product_variants')->cascadeOnDelete();
            $table->foreignId('product_option_value_id')->constrained('aero_shop_product_option_values')->cascadeOnDelete();

            $table->unique(['variant_id', 'product_option_value_id'], 'aero_shop_variant_optval_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_variant_opt_values');
    }
};
