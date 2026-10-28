<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * `reminders_paused`: el tenant apaga los recordatorios automáticos de un cobro.
 * `last_reminder_error`: por qué falló el último intento (límite de Meta, sin
 * WhatsApp, baja…), para que se vea en la ficha del cobro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_crm_collection_items', function (Blueprint $table) {
            $table->boolean('reminders_paused')->default(false);
            $table->string('last_reminder_error', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aero_crm_collection_items', function (Blueprint $table) {
            $table->dropColumn(['reminders_paused', 'last_reminder_error']);
        });
    }
};
