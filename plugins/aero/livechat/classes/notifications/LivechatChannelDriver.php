<?php namespace Aero\Livechat\Classes\Notifications;

use Aero\Hello\Classes\Notifications\ChannelDriverInterface;
use Aero\Hello\Models\Account;
use Aero\Hello\Models\Conversation as HelloConversation;
use Aero\Livechat\Classes\AttachmentStorage;
use Aero\Livechat\Classes\HelloBridge;
use Aero\Livechat\Classes\TelegramBridge;
use Aero\Livechat\Models\Message;

/**
 * Driver de Aero.Hello para las cuentas espejo de livechat (una por Inbox,
 * ver HelloBridge::account). No hay proveedor externo: enviar un mensaje es
 * escribirlo directo en las tablas de livechat (aero_livechat_messages), lo
 * mismo que hacen Conversations::onReply/onAttach del panel — así el
 * visitante lo ve en el widget igual que si un agente le hubiera contestado
 * desde el panel de October.
 *
 * Sin ventana de 24h, plantillas, ubicación/encuesta/citar: son capacidades
 * de WhatsApp que no aplican a un chat de sitio web — al quedar en `false`,
 * el PWA (tema whatsapp) oculta solas esas opciones para este canal.
 */
class LivechatChannelDriver implements ChannelDriverInterface
{
    public function capabilities(): array
    {
        return [
            'text'        => true,
            'media'       => true,
            'templates'   => false,
            'window_24h'  => false,
            'calls'       => false,
            'posts'       => false,
            'location'    => false,
            'contact'     => false,
            'poll'        => false,
            'quote_reply' => false,
        ];
    }

    /** El payload ya viene normalizado (armado por HelloBridge::mirrorInbound); nada que traducir. */
    public function parseWebhook(Account $account, array $payload): array
    {
        return $payload;
    }

    public function sendMessage(Account $account, string $to, array $payload): string
    {
        $conversation = HelloBridge::conversationFromExternalId($payload['conversation_id'] ?? null);
        if (!$conversation) {
            throw new \RuntimeException('Esta conversación de chat web ya no existe.');
        }

        // Quién responde: el agente al que quedó asignada la conversación en
        // Hello (InboxController::reply/attachment se lo asigna solo si
        // estaba libre) — sendMessage no recibe al usuario que disparó el
        // envío, así que se lee del lado ya asignado.
        $agentId = HelloConversation::where('account_id', $account->id)
            ->where('zernio_conversation_id', $payload['conversation_id'])
            ->value('assigned_to');

        $attributes = [
            'conversation_id' => $conversation->id,
            'sender_type'     => Message::AGENT,
            'sender_id'       => $agentId,
            'body'            => $payload['body'] ?? '',
        ];

        $prefix = '👨‍💻 Agente:';

        if (!empty($payload['media_url'])) {
            $stored = AttachmentStorage::storeFromRemote($payload['media_url'], null, null, $payload['media_type'] ?? null);
            if (isset($stored['error'])) {
                throw new \RuntimeException($stored['error']);
            }

            $attributes += [
                'attachment_path'  => $stored['path'],
                'attachment_name'  => $stored['name'],
                'attachment_mime'  => $stored['mime'],
                'attachment_size'  => $stored['size'],
                'attachment_token' => $stored['token'],
            ];
        }

        $message = Message::create($attributes);

        $conversation->last_message_at = now();
        $conversation->agent_unread_count = 0;
        $conversation->visitor_unread_count++;
        $conversation->save();

        if ($message->hasAttachment()) {
            TelegramBridge::relayAttachment($conversation, $message, $prefix);
        } else {
            TelegramBridge::relay($conversation, $message, $prefix);
        }

        return 'lc-msg-' . $message->id;
    }
}
