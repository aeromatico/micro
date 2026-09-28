<?php namespace Aero\Livechat\Classes;

use Aero\Hello\Jobs\ProcessWebhookEventJob;
use Aero\Hello\Models\Account;
use Aero\Hello\Models\WebhookEvent;
use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Inbox;
use Aero\Livechat\Models\Message;

/**
 * Espejo hacia Aero.Hello: cada Inbox de livechat aparece como una cuenta más
 * (`driver` = 'livechat') para que el PWA de aero/chat (tema `whatsapp`) la
 * muestre igual que un número de WhatsApp, sin tocar aero/chat — su
 * InboxController ya es genérico sobre Aero\Hello\Models\Account/Conversation.
 *
 * El id local de la conversación de livechat se guarda en
 * `zernio_conversation_id` (columna genérica heredada de Zernio, igual que
 * wapi guarda ahí su instanceId) con el prefijo "livechat-", así
 * LivechatChannelDriver::sendMessage puede volver de un Message de Hello al
 * Conversation de livechat sin columnas nuevas en aero_livechat_*.
 */
class HelloBridge
{
    public const CONVERSATION_PREFIX = 'livechat-';

    /** Cuenta espejo del inbox: se crea/actualiza cada vez que el inbox se guarda (ver Inbox::boot). */
    public static function account(Inbox $inbox): Account
    {
        return Account::updateOrCreate(
            ['zernio_account_id' => 'livechat-inbox-' . $inbox->id],
            [
                'tenant_id' => $inbox->tenant_id,
                'driver'    => 'livechat',
                'platform'  => 'livechat',
                // Corto y genérico a propósito, igual que "WhatsApp"/"Telegram" en el PWA — el
                // nombre propio del inbox (ej. "Soporte plataforma") sigue siendo lo que ve
                // el visitante en el widget, esto es solo la etiqueta del canal en el omnichat.
                'label'     => 'Livechat',
                'status'    => 'connected',
                'is_enabled' => (bool) $inbox->is_active,
                'connected_at' => now(),
            ]
        );
    }

    public static function conversationExternalId(Conversation $conversation): string
    {
        return self::CONVERSATION_PREFIX . $conversation->id;
    }

    public static function conversationFromExternalId(?string $externalId): ?Conversation
    {
        if (!$externalId || !str_starts_with($externalId, self::CONVERSATION_PREFIX)) {
            return null;
        }

        return Conversation::find((int) substr($externalId, strlen(self::CONVERSATION_PREFIX)));
    }

    /**
     * Refleja un mensaje entrante del widget/Telegram hacia Hello, para que
     * el PWA lo vea llegar como cualquier otro mensaje. Se apoya en el mismo
     * job que procesa los webhooks de Zernio/wapi (ProcessWebhookEventJob),
     * con LivechatChannelDriver::parseWebhook devolviendo el payload tal cual
     * (ya lo armamos acá con la forma normalizada que ese job espera).
     */
    public static function mirrorInbound(Conversation $conversation, Message $message): void
    {
        $inbox = $conversation->inbox ?: Inbox::find($conversation->inbox_id);
        if (!$inbox) {
            return;
        }

        $account = self::account($inbox);
        $contact = $conversation->contact;

        $payload = [
            'external_id'     => 'lc-msg-' . $message->id,
            'conversation_id' => self::conversationExternalId($conversation),
            'from'            => $contact->visitor_token,
            'name'            => $contact->display_name,
            'type'            => $message->hasAttachment() ? ($message->isImageAttachment() ? 'image' : 'document') : 'text',
            'body'            => $message->body !== '' ? $message->body : null,
            'media_url'       => $message->hasAttachment() ? $message->attachment_url : null,
            'timestamp'       => $message->created_at?->timestamp,
        ];

        $eventId = 'livechat:message:' . $message->id;
        $event = WebhookEvent::firstOrNew(['event_id' => $eventId]);
        if ($event->exists) {
            return;
        }

        $event->fill(['event_type' => 'message.received', 'account_id' => $account->id, 'payload' => $payload])->save();

        ProcessWebhookEventJob::dispatch($event->id);
    }
}
