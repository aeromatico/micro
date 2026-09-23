<?php namespace Aero\Livechat\Classes;

use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Message;

/**
 * Cierre de una conversación — mismo procedimiento sin importar quién lo
 * dispara: el visitante (widget), el agente (panel) o el auto-cierre por
 * inactividad (`livechat:auto-finish`). Deja registro, avisa por Telegram y
 * cierra el Topic si lo tiene.
 */
class ConversationLifecycle
{
    public static function finish(Conversation $conversation, string $reason): void
    {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::SYSTEM,
            'body'            => $reason,
        ]);

        $conversation->status = Conversation::RESOLVED;
        $conversation->save();

        TelegramBridge::relay($conversation, $message, 'ℹ️');
        TelegramBridge::closeThreadIfAny($conversation);
    }
}
