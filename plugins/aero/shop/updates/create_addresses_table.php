<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('aero_shop_customers')->cascadeOnDelete();
            $table->string('type')->default('shipping')->comment('shipping|billing');
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city');
            $table->string('state_province')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code', 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_addresses');
    }
};
