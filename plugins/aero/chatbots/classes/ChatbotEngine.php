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

    protected const AI_HISTORY_LIMIT = 8;

    protected const AI_RETRY_ATTEMPTS = 2;

    // `super_ai` todavía se comporta igual que `ai` — el modo en sí está
    // pendiente de una implementación propia (ver Bot::reply_mode).
    protected const AI_REPLY_MODES = ['ai', 'super_ai'];

    protected const DEFAULT_SYSTEM_PROMPT = 'Sos un asistente de atención al cliente por WhatsApp. '
        . 'Respondé breve, claro y en español.';

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

        if ($rule) {
            $responseText = $rule->response_text;
        }
        elseif (in_array($bot->reply_mode, static::AI_REPLY_MODES, true)) {
            // En modo IA no hay fallback al mensaje estático: si la IA falla
            // (error de la API, modelo mal configurado, etc.) preferimos no
            // responder nada antes que mandar un texto genérico que no tiene
            // nada que ver con lo que preguntó el contacto.
            $responseText = static::tryAiReply($bot, $message);
        }
        else {
            $responseText = $bot->fallback_message;
        }

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

    /**
     * Fallback de IA cuando ninguna Rule matchea: usa el Connector elegido
     * en el bot (Aero.Connector es dependencia opcional) con el modelo del
     * catálogo predefinido. Cualquier problema (sin connector, llamada
     * fallida, respuesta vacía) devuelve null y `handle()` cae al
     * `fallback_message` estático de siempre.
     */
    protected static function tryAiReply(Bot $bot, Message $message): ?string
    {
        if (!in_array($bot->reply_mode, static::AI_REPLY_MODES, true) || !$bot->ai_connector_id
            || !class_exists(\Aero\Connector\Models\Connector::class)) {
            return null;
        }

        $connector = $bot->aiConnector;
        if (!$connector || !$connector->is_enabled) {
            return null;
        }

        $messages = static::buildAiMessages($bot, $message);
        $client = app(\Aero\Connector\Classes\ConnectorClient::class);

        // Los fallos de red/rate-limit con proveedores de IA son frecuentes y
        // casi siempre transitorios — sin este reintento, cualquier hiccup
        // deja al contacto sin respuesta y sin ningún rastro (ver `handle()`:
        // en modo IA no hay fallback a mensaje estático).
        for ($attempt = 1; $attempt <= static::AI_RETRY_ATTEMPTS; $attempt++) {
            $response = $client->send($connector, [
                'messages' => $messages,
                'model'    => $bot->ai_model ?: null,
            ]);

            if ($response->successful) {
                break;
            }

            if ($attempt < static::AI_RETRY_ATTEMPTS) {
                usleep(500_000);
            }
        }

        if (!$response->successful) {
            \Log::warning('aero.chatbots: la IA no respondió tras reintentos', [
                'bot_id'          => $bot->id,
                'connector_id'    => $connector->id,
                'inbound_message' => $message->id,
                'status_code'     => $response->statusCode,
                'error'           => $response->error,
                'raw_body'        => $response->rawBody,
            ]);

            return null;
        }

        $replyText = static::extractAiReplyText($connector, $response);
        if (!$replyText) {
            \Log::warning('aero.chatbots: la IA respondió pero no se pudo extraer texto', [
                'bot_id'          => $bot->id,
                'connector_id'    => $connector->id,
                'inbound_message' => $message->id,
                'raw_body'        => $response->rawBody,
            ]);

            return null;
        }

        static::chargeAiCredits($bot, $message);

        return static::toWhatsAppMarkdown($replyText);
    }

    /**
     * Los modelos de IA escriben markdown estándar (**negrita**, __negrita__),
     * pero WhatsApp solo reconoce *un solo asterisco* para negrita y _un solo
     * guion bajo_ para cursiva — con doble símbolo, muestra los símbolos
     * literales en vez de aplicar el formato.
     */
    protected static function toWhatsAppMarkdown(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/s', '*$1*', $text);
        $text = preg_replace('/__(.+?)__/s', '_$1_', $text);

        return $text;
    }

    /**
     * System prompt + un historial corto de la conversación (el mensaje
     * entrante actual queda incluido como el último, al ser ya el más
     * reciente persistido) para que la IA tenga contexto.
     */
    protected static function buildAiMessages(Bot $bot, Message $message): array
    {
        $history = Message::where('conversation_id', $message->conversation_id)
            ->orderByDesc('id')
            ->limit(static::AI_HISTORY_LIMIT)
            ->get()
            ->reverse()
            ->map(fn ($m) => [
                'role'    => $m->direction === 'outbound' ? 'assistant' : 'user',
                'content' => $m->body,
            ])
            ->all();

        return [
            ['role' => 'system', 'content' => $bot->ai_system_prompt ?: static::DEFAULT_SYSTEM_PROMPT],
            ...$history,
        ];
    }

    /**
     * Normaliza la respuesta cruda del connector: no vive en Aero.Connector
     * porque su forma depende de la API (OpenAI-compatible vs Anthropic).
     */
    protected static function extractAiReplyText(\Aero\Connector\Models\Connector $connector, \Aero\Connector\Classes\ConnectorResponse $response): ?string
    {
        $body = is_array($response->body) ? $response->body : json_decode((string) $response->rawBody, true);
        if (!is_array($body)) {
            return null;
        }

        $text = $connector->type === 'ai_anthropic'
            ? ($body['content'][0]['text'] ?? null)
            : ($body['choices'][0]['message']['content'] ?? null);

        return $text ? trim($text) : null;
    }

    /**
     * El costo/color se leen del catálogo global `AiModel` (menú
     * "Configuración", solo superadmin) para el (connector, model) que el
     * bot tiene elegido — el bot ya no define su propio costo.
     *
     * No usa el `credit_cost` del Connector ni el evento automático
     * 'aero.connector.afterRun' de Aero.Credits: ese cobro depende de
     * Credits::resolveCurrentTenantId(), que solo resuelve en contexto
     * backend y acá corremos disparados por un webhook. Se cobra explícito
     * con el tenant_id ya conocido del bot.
     */
    protected static function chargeAiCredits(Bot $bot, Message $message): void
    {
        if (!class_exists(\Aero\Credits\Classes\Credits::class)) {
            return;
        }

        $aiModel = \Aero\Chatbots\Models\AiModel::where('connector_id', $bot->ai_connector_id)
            ->where('model_id', $bot->ai_model)
            ->first();

        if (!$aiModel || !$aiModel->credit_cost) {
            return;
        }

        $creditType = $aiModel->credit_type_id
            ? \Aero\Credits\Models\CreditType::find($aiModel->credit_type_id)
            : \Aero\Credits\Models\CreditType::active()->first();

        if (!$creditType) {
            return;
        }

        try {
            \Aero\Credits\Classes\Credits::chargeRaw(
                $bot->tenant_id,
                $creditType,
                $aiModel->credit_cost,
                'chatbots.ai_reply',
                ['source_plugin' => 'Aero.Chatbots', 'bot_id' => $bot->id, 'ai_model_id' => $aiModel->id, 'conversation_id' => $message->conversation_id]
            );
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            // Sin saldo: la respuesta de IA ya se generó y se manda igual,
            // no le negamos la respuesta al contacto final por esto.
        }
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
