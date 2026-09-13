<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Credenciales de canal propias de un tenant (SMTP propio, bot de Telegram,
 * cuenta Twilio, cuenta/número de WhatsApp explícito). Generaliza
 * aero_sites_notification_channels (que solo alimentaba el contacto) a todo
 * el gateway: Notify::deliverOne() la consulta para CUALQUIER evento con
 * Rule en ese canal, no solo sites.contact.submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aero_notify_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('channel', 30)->comment('email, whatsapp, telegram, sms');
            $table->string('label');
            $table->text('config')->nullable()->comment('JSON cifrado con credenciales/dirección de destino');
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'channel', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aero_notify_channels');
    }
};
