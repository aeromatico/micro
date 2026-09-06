<?php namespace Aero\Chatbots\Classes;

use Aero\Chatbots\Models\Bot;
use Aero\Chatbots\Models\ConversationState;
use Aero\Chatbots\Models\Log;
use Aero\Hello\Classes\Hello;
use Aero\Hello\Models\Message;

/**
 * Escucha `aero.hello.messageReceived` (disparado por
 * ProcessWebhookEventJob::handleMessage tras persistir cada mensaje
 * entrante) y responde automáticamente si la cuenta tiene un bot activo y
 * la conversación no está en handoff humano.
 *
 * El handoff se detecta enganchándose a `Message::extend()` sobre los
 * mensajes salientes: si un mensaje `outbound` se crea y NO fue enviado por
 * este motor (bandera `$sending`), asumimos que lo mandó un agente humano
 * (o la API) y pausamos el bot para esa conversación por
 * `bot.handoff_minutes`.
 */
class ChatbotEngine
{
    protected static bool $sending = false;

    public static function handle(Message $message, array $payload = []): void
    {
        if ($message->direction !== 'inbound' || !$message->body) {
            return;
        }

        $bot = Bot::active()->where('account_id', $message->account_id)->first();
        if (!$bot) {
            return;
        }

        $state = ConversationState::where('conversation_id', $message->conversation_id)->first();
        if ($state && $state->isPaused()) {
            return;
        }

        $rule = $bot->rules()
            ->active()
            ->orderByDesc('priority')
            ->get()
            ->first(fn ($rule) => $rule->matches($message->body));

        $responseText = $rule?->response_text ?: $bot->fallback_message;
        if (!$responseText) {
            return;
        }

        $outbound = static::sendAsBot($bot, $message, $responseText);

        Log::create([
            'bot_id'               => $bot->id,
            'conversation_id'      => $message->conversation_id,
            'inbound_message_id'   => $message->id,
            'outbound_message_id'  => $outbound?->id,
            'rule_id'              => $rule?->id,
            'matched_at'           => now(),
        ]);
    }

    protected static function sendAsBot(Bot $bot, Message $inbound, string $responseText): ?Message
    {
        static::$sending = true;

        try {
            return Hello::sendToContact(
                \Aero\Hello\Models\Contact::find($inbound->contact_id),
                $responseText,
                ['account_id' => $bot->account_id]
            );
        }
        finally {
            static::$sending = false;
        }
    }

    /**
     * Enganchado a `Message::extend()` desde Plugin::boot(). Un mensaje
     * saliente que el motor no mandó él mismo es una respuesta humana (backend
     * o API): pausa el bot de esa conversación para no pisarla.
     */
    public static function handleOutbound(Message $message): void
    {
        if (static::$sending || $message->direction !== 'outbound') {
            return;
        }

        $bot = Bot::where('account_id', $message->account_id)->first();
        if (!$bot) {
            return;
        }

        ConversationState::updateOrCreate(
            ['conversation_id' => $message->conversation_id],
            ['bot_id' => $bot->id, 'paused_until' => now()->addMinutes($bot->handoff_minutes)]
        );
    }
}
