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
    /**
     * $auto = true solo desde el cierre automático por inactividad (nadie lo
     * finalizó a mano) — abre un ticket de CRM con el historial para no
     * perder el lead. Ver LeadTicket::openFor.
     */
    public static function finish(Conversation $conversation, string $reason, bool $auto = false): void
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

        if ($auto) {
            LeadTicket::openFor($conversation);
        }
    }
}
