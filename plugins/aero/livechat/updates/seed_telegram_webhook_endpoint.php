<?php

use October\Rain\Database\Updates\Migration;
use Illuminate\Support\Str;

/**
 * Un solo WebhookEndpoint para TODOS los bots/inboxes de Telegram: la entrega
 * de Telegram no dice qué bot la mandó, pero sí el chat_id, y eso alcanza
 * para resolver el inbox (ver Aero\Livechat\Classes\TelegramBridge::handleInbound).
 * Cada bot registra su webhook apuntando a esta misma URL pública.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!class_exists(\Aero\Connector\Models\WebhookEndpoint::class)) {
            return;
        }

        \Aero\Connector\Models\WebhookEndpoint::firstOrCreate(
            ['slug' => 'livechat-telegram'],
            [
                'name'             => 'Livechat: Telegram',
                'type'             => 'telegram',
                'verification'     => 'header_token',
                'signature_header' => 'X-Telegram-Bot-Api-Secret-Token',
                'dispatch_event'   => 'aero.livechat.telegram_inbound',
                'is_enabled'       => true,
                'secret'           => Str::random(48),
            ]
        );
    }

    public function down(): void
    {
        if (!class_exists(\Aero\Connector\Models\WebhookEndpoint::class)) {
            return;
        }

        \Aero\Connector\Models\WebhookEndpoint::where('slug', 'livechat-telegram')->delete();
    }
};
