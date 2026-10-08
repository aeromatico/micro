<?php namespace Aero\Livechat\Classes;

use Aero\Connector\Classes\ConnectorClient;
use Aero\Connector\Classes\TypeRegistry;
use Aero\Connector\Drivers\TelegramDriver;
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
 *  - Varias conversaciones simultáneas pueden compartir el mismo chat/grupo
 *    de Telegram. Si ese grupo tiene "Topics" habilitado (Ajustes del grupo
 *    → Topics → On), cada conversación abre su propio hilo (Topic) la
 *    primera vez que se le manda algo — así no se mezclan visualmente y la
 *    resolución de "esta respuesta es de qué conversación" es exacta
 *    (`message_thread_id`), sin depender de que el agente cite el mensaje.
 *  - Sin Topics (grupo común, o chat 1 a 1 con el bot), se sigue agrupando
 *    con "reply" al último mensaje reenviado de esa conversación, y la
 *    resolución entrante cae al `telegram_message_id` citado — mismo
 *    comportamiento que antes de sumar Topics.
 */
class TelegramBridge
{
    /** Reenvía un mensaje recién creado (visitante o agente) a Telegram, si el inbox tiene bridge configurado. */
    public static function relay(Conversation $conversation, Message $message, string $prefix): void
    {
        [$inbox, $connector] = static::resolveBridge($conversation);
        if (!$inbox || !$connector) {
            return;
        }

        $threadId = static::resolveThreadId($conversation, $inbox, $connector);

        $response = app(ConnectorClient::class)->send($connector, array_filter([
            'chat_id'             => $inbox->telegram_chat_id,
            'text'                => trim($prefix . "\n" . $message->body),
            'message_thread_id'   => $threadId,
            'reply_to_message_id' => $threadId ? null : static::anchorFor($conversation),
        ], fn ($v) => $v !== null && $v !== ''));

        $sentId = $response->body['result']['message_id'] ?? null;
        if ($response->successful && $sentId) {
            $message->newQuery()->where('id', $message->id)->update(['telegram_message_id' => $sentId]);
        }
    }

    /**
     * Igual que `relay()` pero para un mensaje con adjunto: Telegram baja el
     * archivo solo, a partir de la URL pública (no se reenvían bytes desde
     * acá) — sendPhoto para imágenes (con preview), sendDocument para el resto.
     */
    public static function relayAttachment(Conversation $conversation, Message $message, string $prefix): void
    {
        if (!$message->hasAttachment()) {
            return;
        }

        [$inbox, $connector] = static::resolveBridge($conversation);
        $driver = TypeRegistry::driverFor('telegram');
        if (!$inbox || !$connector || !$driver instanceof TelegramDriver) {
            return;
        }

        $threadId = static::resolveThreadId($conversation, $inbox, $connector);

        $response = $driver->sendMedia(
            $connector,
            (string) $inbox->telegram_chat_id,
            $message->attachment_url,
            $message->isImageAttachment() ? 'photo' : 'document',
            trim($prefix . "\n" . $message->attachment_name),
            $threadId ? null : static::anchorFor($conversation, excludeMessageId: $message->id),
            $threadId
        );

        $sentId = $response->body['result']['message_id'] ?? null;
        if ($response->successful && $sentId) {
            $message->newQuery()->where('id', $message->id)->update(['telegram_message_id' => $sentId]);
        }
    }

    /** @return array{0: ?Inbox, 1: ?Connector} */
    protected static function resolveBridge(Conversation $conversation): array
    {
        $inbox = $conversation->inbox;
        if (!$inbox || !$inbox->telegram_connector_id || !$inbox->telegram_chat_id) {
            return [null, null];
        }

        return [$inbox, Connector::find($inbox->telegram_connector_id)];
    }

    /** Último `telegram_message_id` ya enviado de esta conversación — ancla del "reply" cuando no hay Topic. */
    protected static function anchorFor(Conversation $conversation, ?int $excludeMessageId = null): ?int
    {
        return Message::where('conversation_id', $conversation->id)
            ->whereNotNull('telegram_message_id')
            ->when($excludeMessageId, fn ($q) => $q->where('id', '!=', $excludeMessageId))
            ->orderByDesc('id')
            ->value('telegram_message_id');
    }

    /**
     * Devuelve el Topic de esta conversación, creándolo la primera vez. NULL
     * si el chat no soporta Topics (se intenta una sola vez: el resultado
     * "no aplica" se guarda como 0 para no reintentar en cada mensaje).
     */
    protected static function resolveThreadId(Conversation $conversation, Inbox $inbox, Connector $connector): ?int
    {
        if ($conversation->telegram_thread_id !== null) {
            return $conversation->telegram_thread_id ?: null;
        }

        $driver = TypeRegistry::driverFor('telegram');
        if (!$driver instanceof TelegramDriver) {
            return null;
        }

        $name = trim(($conversation->contact?->display_name ?: 'Visitante') . " (#{$conversation->id})");
        $response = $driver->createForumTopic($connector, (string) $inbox->telegram_chat_id, $name);
        $threadId = $response->body['result']['message_thread_id'] ?? null;

        $conversation->newQuery()->where('id', $conversation->id)->update(['telegram_thread_id' => $threadId ?: 0]);
        $conversation->telegram_thread_id = $threadId ?: 0;

        return $threadId;
    }

    /** Al finalizar una conversación (desde el widget o el panel), cierra su Topic si tiene uno. */
    public static function closeThreadIfAny(Conversation $conversation): void
    {
        if (!$conversation->telegram_thread_id) {
            return;
        }

        [$inbox, $connector] = static::resolveBridge($conversation);
        $driver = TypeRegistry::driverFor('telegram');
        if (!$inbox || !$connector || !$driver instanceof TelegramDriver) {
            return;
        }

        $driver->closeForumTopic($connector, (string) $inbox->telegram_chat_id, $conversation->telegram_thread_id);
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

        $threadId = $msg['message_thread_id'] ?? null;
        $replyToId = $msg['reply_to_message']['message_id'] ?? null;

        $conversation = $threadId
            ? static::findConversationByThread($chatId, (int) $threadId)
            : ($replyToId
                ? static::findConversationByRepliedMessage((int) $replyToId, $chatId)
                : static::findLatestOpenConversation($chatId));

        if (!$conversation) {
            static::notifyUnresolved($chatId, (int) ($msg['message_id'] ?? 0), $threadId ? (int) $threadId : null);
            return;
        }

        Message::create([
            'conversation_id'     => $conversation->id,
            'sender_type'         => Message::AGENT,
            'sender_id'           => null,
            'body'                => $text,
            'telegram_message_id' => $msg['message_id'] ?? null,
        ]);

        $conversation->last_message_at = now();
        $conversation->visitor_unread_count++;
        $conversation->save();
    }

    protected static function findConversationByThread(string $chatId, int $threadId): ?Conversation
    {
        $inbox = Inbox::where('telegram_chat_id', $chatId)->first();
        if (!$inbox) {
            return null;
        }

        return Conversation::where('inbox_id', $inbox->id)->where('telegram_thread_id', $threadId)->first();
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

    /** No se pudo resolver a qué conversación pertenece: se avisa en el propio chat/hilo de Telegram. */
    protected static function notifyUnresolved(string $chatId, int $replyToMessageId, ?int $threadId = null): void
    {
        $inbox = Inbox::where('telegram_chat_id', $chatId)->first();
        $connector = $inbox?->telegram_connector_id ? Connector::find($inbox->telegram_connector_id) : null;
        if (!$connector) {
            return;
        }

        app(ConnectorClient::class)->send($connector, array_filter([
            'chat_id'              => $chatId,
            'text'                 => 'No identifiqué a qué conversación corresponde. Respondé citando (reply) el mensaje del visitante que querés contestar.',
            'reply_to_message_id'  => $threadId ? null : ($replyToMessageId ?: null),
            'message_thread_id'    => $threadId,
        ], fn ($v) => $v !== null && $v !== ''));
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
        if (!$driver instanceof TelegramDriver) {
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
        if (!$driver instanceof TelegramDriver) {
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
