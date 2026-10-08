<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Puente Telegram (fase 1). El mismo patrón (connector_id + chat_id en el
 * inbox, telegram_message_id en el mensaje para "responder citando") sirve
 * para sumar otros canales de agente más adelante — ej. WhatsApp Business
 * Cloud API tendría su propio par whatsapp_connector_id/whatsapp_phone_id y
 * un whatsapp_message_id en el mensaje, reusando Aero\Connector igual que acá.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aero_livechat_inboxes', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_connector_id')->nullable()->after('is_active');
            $table->string('telegram_chat_id')->nullable()->after('telegram_connector_id');
        });

        Schema::table('aero_livechat_messages', function (Blueprint $table) {
            $table->bigInteger('telegram_message_id')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('aero_livechat_messages', function (Blueprint $table) {
            $table->dropColumn('telegram_message_id');
        });

        Schema::table('aero_livechat_inboxes', function (Blueprint $table) {
            $table->dropColumn(['telegram_connector_id', 'telegram_chat_id']);
        });
    }
};
