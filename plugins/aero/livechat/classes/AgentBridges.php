<?php namespace Aero\Livechat\Classes;

use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Message;

/**
 * Un solo punto para retransmitir un mensaje a todos los canales de agente
 * (Telegram, WhatsApp vía Hello) — así cada lugar que crea mensajes no tiene
 * que conocer cuántos puentes hay.
 */
class AgentBridges
{
    public static function relay(Conversation $conversation, Message $message, string $prefix): void
    {
        TelegramBridge::relay($conversation, $message, $prefix);
        WhatsappBridge::relay($conversation, $message, $prefix);
    }

    public static function relayAttachment(Conversation $conversation, Message $message, string $prefix): void
    {
        TelegramBridge::relayAttachment($conversation, $message, $prefix);
        WhatsappBridge::relay($conversation, $message, $prefix);
    }
}
