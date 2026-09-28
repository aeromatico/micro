<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_wpflash_customer_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('wp_customer_id');
            $table->unsignedBigInteger('shop_customer_id')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'wp_customer_id']);
            $table->index('shop_customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_wpflash_customer_links');
    }
};
