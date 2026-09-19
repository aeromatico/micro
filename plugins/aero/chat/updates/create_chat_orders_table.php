<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/** Pedidos de la tienda creados desde una conversación: qué chat y qué agente los originó. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_chat_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedBigInteger('order_id')->unique();
            $table->boolean('notify_on_paid')->default(true);
            $table->timestamp('paid_notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_chat_orders');
    }
};
