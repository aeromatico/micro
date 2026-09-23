<?php namespace Aero\Livechat\Classes;

use Aero\Connector\Classes\ConnectorClient;
use Aero\Connector\Classes\TypeRegistry;
use Aero\Connector\Models\Connector;
use Aero\Connector\Models\WebhookEndpoint;
use Aero\Livechat\Models\Conversation;
use Aero\Livechat\Models\Inbox;
use Aero\Livechat\Models\Message;

/**
 * Puente bidireccional entre una conversación del widget y un chat/grupo de
 * Telegram, vía Aero.Connector. Diseño (pensado para sumar otros canales de
 * agente después, ej. WhatsApp Business Cloud API, con el mismo patrón):
 *
 *  - Saliente: cada mensaje (del visitante o del agente desde el panel) se
 *    reenvía a Telegram como respuesta ("reply") al último mensaje de esa
 *    misma conversación ya reenviado — así Telegram agrupa el hilo visualmente
 *    aunque varias conversaciones compartan el mismo chat/grupo.
 *  - Entrante: cuando el agente responde en Telegram CITANDO (reply) un
 *    mensaje del bot, se resuelve la conversación por el `telegram_message_id`
 *    guardado en ese mensaje — nunca por texto libre. Sin cita, se usa la
 *    conversación abierta más reciente del inbox que corresponde a ese chat_id.
 */
class TelegramBridge
{
    /** Reenvía un mensaje recién creado (visitante o agente) a Telegram, si el inbox tiene bridge configurado. */
    public static function relay(Conversation $conversation, Message $message, string $prefix): void
    {
        $inbox = $conversation->inbox;
        if (!$inbox || !$inbox->telegram_connector_id || !$inbox->telegram_chat_id) {
            return;
        }

        $connector = Connector::find($inbox->telegram_connector_id);
        if (!$connector) {
            return;
        }

        $anchor = Message::where('conversation_id', $conversation->id)
            ->whereNotNull('telegram_message_id')
            ->orderByDesc('id')
            ->value('telegram_message_id');

        $response = app(ConnectorClient::class)->send($connector, array_filter([
            'chat_id'             => $inbox->telegram_chat_id,
            'text'                => trim($prefix . "\n" . $message->body),
            'reply_to_message_id' => $anchor,
        ], fn ($v) => $v !== null && $v !== ''));

        $sentId = $response->body['result']['message_id'] ?? null;
        if ($response->successful && $sentId) {
            $message->newQuery()->where('id', $message->id)->update(['telegram_message_id' => $sentId]);
        }
    }

    /** Handler del evento `aero.livechat.telegram_inbound` — un update de Telegram. */
    public static function handleInbound(array $payload): void
    {
        $msg = $payload['message'] ?? $payload['edited_message'] ?? null;
        $text = trim((string) ($msg['text'] ?? ''));
        $chatId = (string) ($msg['chat']['id'] ?? '');

        if (!$msg || $text === '' || $chatId === '') {
            return;
        }

        $replyToId = $msg['reply_to_message']['message_id'] ?? null;

        $conversation = $replyToId
            ? static::findConversationByRepliedMessage((int) $replyToId, $chatId)
            : static::findLatestOpenConversation($chatId);

        if (!$conversation) {
            static::notifyUnresolved($chatId, (int) ($msg['message_id'] ?? 0));
            return;
        }

        $agentMessage = Message::create([
            'conversation_id'      => $conversation->id,
            'sender_type'          => Message::AGENT,
            'sender_id'            => null,
            'body'                 => $text,
            'telegram_message_id'  => $msg['message_id'] ?? null,
        ]);

        $conversation->last_message_at = now();
        $conversation->visitor_unread_count++;
        $conversation->save();
    }

    protected static function findConversationByRepliedMessage(int $telegramMessageId, string $chatId): ?Conversation
    {
        $message = Message::where('telegram_message_id', $telegramMessageId)
            ->whereHas('conversation.inbox', fn ($q) => $q->where('telegram_chat_id', $chatId))
            ->first();

        return $message?->conversation;
    }

    protected static function findLatestOpenConversation(string $chatId): ?Conversation
    {
        $inbox = Inbox::where('telegram_chat_id', $chatId)->first();
        if (!$inbox) {
            return null;
        }

        return Conversation::where('inbox_id', $inbox->id)
            ->where('status', Conversation::OPEN)
            ->orderByDesc('last_message_at')
            ->first();
    }

    /** No se pudo resolver a qué conversación pertenece: se avisa en el propio chat de Telegram. */
    protected static function notifyUnresolved(string $chatId, int $replyToMessageId): void
    {
        $inbox = Inbox::where('telegram_chat_id', $chatId)->first();
        $connector = $inbox?->telegram_connector_id ? Connector::find($inbox->telegram_connector_id) : null;
        if (!$connector) {
            return;
        }

        app(ConnectorClient::class)->send($connector, [
            'chat_id'              => $chatId,
            'text'                 => 'No identifiqué a qué conversación corresponde. Respondé citando (reply) el mensaje del visitante que querés contestar.',
            'reply_to_message_id'  => $replyToMessageId ?: null,
        ]);
    }

    /**
     * Lee las últimas entregas pendientes del bot (getUpdates) y devuelve el
     * chat del mensaje más reciente — solo funciona ANTES de registrar un
     * webhook (Telegram deja de acumular updates apenas hay uno activo), por
     * eso `connect()` lo llama antes de `setWebhook`.
     */
    public static function discoverChatId(Connector $connector): ?array
    {
        $driver = TypeRegistry::driverFor('telegram');
        if (!$driver instanceof \Aero\Connector\Drivers\TelegramDriver) {
            return null;
        }

        $response = $driver->getUpdates($connector);
        $updates = $response->body['result'] ?? [];
        if (!$response->successful || !$updates) {
            return null;
        }

        $chat = end($updates)['message']['chat'] ?? null;
        if (!$chat) {
            return null;
        }

        return ['id' => $chat['id'], 'label' => $chat['title'] ?? $chat['first_name'] ?? (string) $chat['id']];
    }

    /**
     * Crea/actualiza el WebhookEndpoint compartido y registra el webhook en
     * Telegram con el token del connector dado. Usado por el botón "Conectar"
     * del form de Inbox.
     */
    public static function connect(Connector $connector, ?string &$error = null): bool
    {
        $endpoint = WebhookEndpoint::where('slug', 'livechat-telegram')->first();
        if (!$endpoint) {
            $error = 'Falta el WebhookEndpoint "livechat-telegram" — corré las migraciones del plugin.';
            return false;
        }

        $driver = TypeRegistry::driverFor('telegram');
        if (!$driver instanceof \Aero\Connector\Drivers\TelegramDriver) {
            $error = 'El driver de Telegram no está disponible.';
            return false;
        }

        $response = $driver->setWebhook($connector, $endpoint->public_url, $endpoint->secret);

        if (!$response->successful) {
            $error = $response->error ?: 'Telegram rechazó el webhook.';
            return false;
        }

        return true;
    }
}
