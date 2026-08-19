<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_tenant_currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('aero_shop_currencies')->cascadeOnDelete();
            $table->decimal('exchange_rate', 18, 6)->comment('Tasa respecto a la moneda base del tenant');
            $table->boolean('is_default')->default(false);
            $table->timestamp('updated_manually_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'currency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_tenant_currencies');
    }
};
