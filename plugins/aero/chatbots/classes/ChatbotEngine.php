<?php namespace Aero\Chatbots\Classes;

use Aero\Chatbots\Models\Bot;
use Aero\Chatbots\Models\ConversationState;
use Aero\Chatbots\Models\Log;
use Aero\Connector\Classes\ConnectorClient;
use Aero\Connector\Classes\ConnectorResponse;
use Aero\Connector\Models\Connector;
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

    // Máximo de round-trips al connector dentro de un mismo mensaje en modo
    // Súper IA (respuesta directa + N llamadas con tool_calls). Sin este
    // límite, un modelo que no converge a una respuesta final dejaría al
    // engine en un loop de llamadas reales (y cobros) indefinido.
    protected const AI_TOOL_LOOP_MAX_ITERATIONS = 3;

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
     *
     * En modo `super_ai` corre un loop de tool-calling (`runSuperAiLoop`) en
     * vez de una única llamada; en `ai` sigue siendo la llamada simple de
     * siempre (`runSimpleAiReply`).
     */
    protected static function tryAiReply(Bot $bot, Message $message): ?string
    {
        if (!in_array($bot->reply_mode, static::AI_REPLY_MODES, true) || !$bot->ai_connector_id
            || !class_exists(Connector::class)) {
            return null;
        }

        $connector = $bot->aiConnector;
        if (!$connector || !$connector->is_enabled) {
            return null;
        }

        $messages = static::buildAiMessages($bot, $message);
        $client = app(ConnectorClient::class);

        $replyText = $bot->reply_mode === 'super_ai'
            ? static::runSuperAiLoop($bot, $connector, $client, $messages, $message)
            : static::runSimpleAiReply($bot, $connector, $client, $messages, $message);

        return $replyText ? static::toWhatsAppMarkdown($replyText) : null;
    }

    /**
     * Modo `ai` clásico: una sola llamada (con reintentos ante fallos de
     * red/rate-limit), sin tools.
     */
    protected static function runSimpleAiReply(Bot $bot, Connector $connector, ConnectorClient $client, array $messages, Message $message): ?string
    {
        $response = static::sendWithRetries($client, $bot, $connector, $message, [
            'messages' => $messages,
            'model'    => $bot->ai_model ?: null,
        ]);

        if (!$response) {
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

        return $replyText;
    }

    /**
     * Modo `super_ai`: arma las tools habilitadas para el bot
     * (`AiToolRegistry::forBot`), se las pasa al connector y, si la
     * respuesta trae `tool_calls`, ejecuta el/los handler(s) correspondiente
     * (siempre con el tenant_id del bot, nunca el que "diga" la IA), agrega
     * el resultado a la conversación y repite hasta obtener texto final o
     * agotar `AI_TOOL_LOOP_MAX_ITERATIONS`.
     *
     * Cada round-trip real al connector se cobra en créditos (no solo la
     * respuesta final) — es el costo real que factura el proveedor de IA.
     */
    protected static function runSuperAiLoop(Bot $bot, Connector $connector, ConnectorClient $client, array $messages, Message $message): ?string
    {
        $tools = AiToolRegistry::forBot($bot);
        $providerTools = $tools ? AiToolRegistry::toProviderFormat($connector->type, $tools) : [];

        // Memoiza resultados de tools dentro del mismo turno: si la IA pide
        // la misma tool con los mismos argumentos más de una vez en este
        // mensaje, se reutiliza el resultado en vez de pegarle a la BD de
        // nuevo. No hay cache entre mensajes/conversaciones en esta fase.
        $callCache = [];

        for ($iteration = 1; $iteration <= static::AI_TOOL_LOOP_MAX_ITERATIONS; $iteration++) {
            $payload = [
                'messages' => $messages,
                'model'    => $bot->ai_model ?: null,
            ];

            if ($providerTools) {
                $payload['tools'] = $providerTools;
                $payload['tool_choice'] = 'auto';
            }

            $response = static::sendWithRetries($client, $bot, $connector, $message, $payload);
            if (!$response) {
                return null;
            }

            static::chargeAiCredits($bot, $message);

            $toolCalls = static::extractToolCalls($connector, $response);

            if (!$toolCalls) {
                $replyText = static::extractAiReplyText($connector, $response);
                if (!$replyText) {
                    \Log::warning('aero.chatbots: Súper IA respondió sin texto ni tool_calls', [
                        'bot_id'          => $bot->id,
                        'connector_id'    => $connector->id,
                        'inbound_message' => $message->id,
                        'raw_body'        => $response->rawBody,
                    ]);

                    return null;
                }

                return $replyText;
            }

            $messages[] = static::buildAssistantToolCallMessage($connector, $response);

            $results = [];
            foreach ($toolCalls as $call) {
                $cacheKey = $call['name'] . ':' . json_encode($call['arguments']);

                if (!array_key_exists($cacheKey, $callCache)) {
                    $callCache[$cacheKey] = static::executeTool($bot, $call['name'], $call['arguments']);
                }

                $results[] = ['id' => $call['id'], 'result' => $callCache[$cacheKey]];
            }

            foreach (static::buildToolResultMessages($connector, $results) as $toolResultMessage) {
                $messages[] = $toolResultMessage;
            }
        }

        \Log::warning('aero.chatbots: Súper IA agotó el límite de iteraciones de tool-calling', [
            'bot_id'          => $bot->id,
            'connector_id'    => $connector->id,
            'inbound_message' => $message->id,
        ]);

        return null;
    }

    /**
     * Ejecuta el handler de una tool registrada. El tenant_id SIEMPRE es el
     * del bot (`$bot->tenant_id`) — nunca uno que venga dentro de
     * `$arguments`, aunque la IA lo mande, para no abrir una fuga de datos
     * entre tenants.
     */
    protected static function executeTool(Bot $bot, ?string $name, array $arguments): mixed
    {
        $tool = $name ? AiToolRegistry::find($name) : null;

        if (!$tool || empty($tool['handler']) || !is_callable($tool['handler'])) {
            return ['error' => "Tool desconocida: {$name}"];
        }

        unset($arguments['tenant_id']);

        try {
            return call_user_func($tool['handler'], $arguments, $bot->tenant_id);
        }
        catch (\Throwable $e) {
            \Log::error('aero.chatbots: falló la ejecución de una AI tool', [
                'tool'   => $name,
                'bot_id' => $bot->id,
                'error'  => $e->getMessage(),
            ]);

            return ['error' => 'La herramienta falló al ejecutarse.'];
        }
    }

    /**
     * Llama al connector con reintentos ante fallos transitorios (red,
     * rate-limit) — comunes con proveedores de IA. Devuelve null (y deja un
     * warning en el log) si ninguno de los intentos tuvo éxito.
     */
    protected static function sendWithRetries(ConnectorClient $client, Bot $bot, Connector $connector, Message $message, array $payload): ?ConnectorResponse
    {
        for ($attempt = 1; $attempt <= static::AI_RETRY_ATTEMPTS; $attempt++) {
            $response = $client->send($connector, $payload);

            if ($response->successful) {
                return $response;
            }

            if ($attempt < static::AI_RETRY_ATTEMPTS) {
                usleep(500_000);
            }
        }

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
     *
     * El system prompt siempre va como el primer mensaje con `role: system`
     * (formato OpenAI) por simplicidad — si el connector es Anthropic,
     * `AiAnthropicDriver` lo extrae y lo manda aparte como parámetro
     * `system` de nivel superior, como exige esa API.
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
    protected static function extractAiReplyText(Connector $connector, ConnectorResponse $response): ?string
    {
        $body = static::decodeResponseBody($response);
        if (!is_array($body)) {
            return null;
        }

        $text = $connector->type === 'ai_anthropic'
            ? static::firstAnthropicTextBlock($body['content'] ?? [])
            : ($body['choices'][0]['message']['content'] ?? null);

        return $text ? trim($text) : null;
    }

    /**
     * Extrae las tool_calls de una respuesta, normalizadas a
     * `[{id, name, arguments}]` sin importar el proveedor. Vacío si el
     * modelo no pidió ejecutar ninguna tool (respuesta de texto directa).
     */
    protected static function extractToolCalls(Connector $connector, ConnectorResponse $response): array
    {
        $body = static::decodeResponseBody($response);
        if (!is_array($body)) {
            return [];
        }

        if ($connector->type === 'ai_anthropic') {
            $calls = [];

            foreach ($body['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'tool_use') {
                    $calls[] = [
                        'id'        => $block['id'] ?? null,
                        'name'      => $block['name'] ?? null,
                        'arguments' => is_array($block['input'] ?? null) ? $block['input'] : [],
                    ];
                }
            }

            return $calls;
        }

        $calls = [];

        foreach ($body['choices'][0]['message']['tool_calls'] ?? [] as $rawCall) {
            $arguments = json_decode($rawCall['function']['arguments'] ?? '{}', true);

            $calls[] = [
                'id'        => $rawCall['id'] ?? null,
                'name'      => $rawCall['function']['name'] ?? null,
                'arguments' => is_array($arguments) ? $arguments : [],
            ];
        }

        return $calls;
    }

    /**
     * El turno "assistant" que pidió las tool_calls hay que reinyectarlo tal
     * cual en la conversación antes de mandar los resultados — ambas APIs
     * (OpenAI y Anthropic) lo exigen para mantener coherencia del hilo.
     */
    protected static function buildAssistantToolCallMessage(Connector $connector, ConnectorResponse $response): array
    {
        $body = static::decodeResponseBody($response);

        if ($connector->type === 'ai_anthropic') {
            return ['role' => 'assistant', 'content' => $body['content'] ?? []];
        }

        return $body['choices'][0]['message'] ?? ['role' => 'assistant', 'content' => null];
    }

    /**
     * Mensaje(s) con el resultado de cada tool ejecutada, en el formato que
     * cada proveedor espera: OpenAI usa un mensaje `role: tool` por cada
     * llamada; Anthropic agrupa todos los resultados del mismo turno en un
     * solo mensaje `role: user` con bloques `tool_result`.
     */
    protected static function buildToolResultMessages(Connector $connector, array $results): array
    {
        if ($connector->type === 'ai_anthropic') {
            $content = array_map(fn ($result) => [
                'type'        => 'tool_result',
                'tool_use_id' => $result['id'],
                'content'     => json_encode($result['result']),
            ], $results);

            return [['role' => 'user', 'content' => $content]];
        }

        return array_map(fn ($result) => [
            'role'         => 'tool',
            'tool_call_id' => $result['id'],
            'content'      => json_encode($result['result']),
        ], $results);
    }

    protected static function decodeResponseBody(ConnectorResponse $response): mixed
    {
        return is_array($response->body) ? $response->body : json_decode((string) $response->rawBody, true);
    }

    protected static function firstAnthropicTextBlock(array $blocks): ?string
    {
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                return $block['text'] ?? null;
            }
        }

        return null;
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
     *
     * En modo Súper IA se llama una vez por cada round-trip real
     * (`runSuperAiLoop`), no solo al final — cada llamada es un costo real
     * facturado por el proveedor.
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
