<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Baja del sistema de notificaciones legacy (NotificationChannel/
 * NotificationDispatcher, ver classes/notifications/ — ya eliminado). Sus 2
 * filas de producción ya se copiaron a aero_notify_channels
 * (Aero.Notify\updates\migrate_legacy_notification_channels.php, v1.3.0 de
 * ese plugin) antes de correr esta baja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('aero_sites_notification_channels');
    }

    public function down(): void
    {
        Schema::create('aero_sites_notification_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('aero_sites_tenants')->cascadeOnDelete();
            $table->string('type')->comment('Driver: email, whatsapp, telegram, sms');
            $table->string('label');
            $table->text('config')->nullable()->comment('JSON encriptado con credenciales');
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'is_enabled']);
        });
    }
};
