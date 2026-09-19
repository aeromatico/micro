<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Cobros rápidos por QR emitidos desde una conversación. Guarda a qué chat y a
 * qué agente pertenece cada QR de Aero.Pay, para poder procesar el pago
 * cuando llega y mostrar el estado en la pestaña Cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_chat_charges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedBigInteger('qr_code_id')->unique();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 8)->default('BOB');
            $table->string('description', 255)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->boolean('notify_on_paid')->default(true);
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_chat_charges');
    }
};
