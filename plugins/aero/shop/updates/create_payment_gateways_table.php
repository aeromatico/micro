<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('driver')->default('manual')->comment('manual hoy; extensible a stripe, mercadopago, etc.');
            $table->string('name');
            $table->longText('instructions')->nullable()->comment('Solo driver manual: datos de cuenta/QR/WhatsApp');
            $table->json('config')->nullable()->comment('Estructura extensible: credenciales de drivers API-driven futuros');
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_manual_confirmation')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_payment_gateways');
    }
};
