<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Botón de emergencia de la página del pedido: avisos que ven la cocina y la caja hasta que alguien los atiende. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_shop_panic_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('order_id');
            $table->string('table_label', 30)->nullable();
            $table->string('label', 120);                      // «Mesa 4» o, sin mesa, «Pedido 18 · Ana»
            $table->string('ip', 45)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->string('acknowledged_by', 80)->nullable(); // cocina | caja | nombre
            $table->timestamps();
            $table->index(['tenant_id', 'acknowledged_at', 'created_at'], 'aero_shop_panic_active');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_shop_panic_alerts');
    }
};
