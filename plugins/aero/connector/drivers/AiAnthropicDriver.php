<?php namespace Aero\Connector\Drivers;

use Http;
use Throwable;
use Aero\Connector\Classes\ConnectorResponse;
use Aero\Connector\Contracts\ConnectorDriver;
use Aero\Connector\Models\Connector;

/**
 * API de Anthropic (Claude): headers `x-api-key` + `anthropic-version`,
 * endpoint `/v1/messages`.
 */
class AiAnthropicDriver implements ConnectorDriver
{
    protected const DEFAULT_VERSION = '2023-06-01';

    public function send(Connector $connector, array $payload = []): ConnectorResponse
    {
        $payload['messages'] = $payload['messages'] ?? [
            ['role' => 'user', 'content' => $payload['prompt'] ?? ''],
        ];

        return $this->messages($connector, $payload);
    }

    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        $overridePayload['messages'] = $overridePayload['messages'] ?? [
            ['role' => 'user', 'content' => $overridePayload['prompt'] ?? 'Responde solo "ok" si me recibes.'],
        ];

        return $this->messages($connector, $overridePayload);
    }

    /**
     * `$payload['tools']`/`$payload['tool_choice']`, si vienen, ya están en
     * el formato que espera esta API (ver
     * Aero\Chatbots\Classes\AiToolRegistry::toProviderFormat) — este driver
     * solo los reenvía tal cual.
     */
    protected function messages(Connector $connector, array $payload): ConnectorResponse
    {
        $config = (array) $connector->config;
        $apiKey = $connector->credentials['api_key'] ?? null;
        $model = $payload['model'] ?? null;
        $model = $model ?: ($config['model'] ?? 'claude-sonnet-5');
        $baseUrl = rtrim($connector->resolvedBaseUrl() ?: 'https://api.anthropic.com', '/');

        // La API de Anthropic no acepta role "system" dentro de `messages`
        // (a diferencia del formato OpenAI) — va aparte como parámetro de
        // nivel superior. Los consumidores (ej. ChatbotEngine::buildAiMessages)
        // arman el system prompt como el primer mensaje del array por
        // simplicidad/compatibilidad con OpenAI; acá se extrae antes de
        // armar el body.
        $messages = [];
        $systemPrompts = [];

        foreach ($payload['messages'] as $message) {
            if (($message['role'] ?? null) === 'system') {
                $systemPrompts[] = $message['content'];
            }
            else {
                $messages[] = $message;
            }
        }

        $body = [
            'model'      => $model,
            // 256 por defecto alcanzaba para una respuesta corta de chat,
            // pero corta a mitad cualquier respuesta larga (ej. el JSON de
            // una página completa que pide Aero.Sites) — payload['max_tokens']
            // deja que el consumidor pida más cuando lo necesita.
            'max_tokens' => $payload['max_tokens'] ?? $config['max_tokens'] ?? 4096,
            'messages'   => $messages,
        ];

        if ($systemPrompts) {
            $body['system'] = implode("\n\n", $systemPrompts);
        }

        if (!empty($payload['tools'])) {
            $body['tools'] = $payload['tools'];

            $toolChoice = $payload['tool_choice'] ?? null;
            if ($toolChoice) {
                $body['tool_choice'] = is_array($toolChoice) ? $toolChoice : ['type' => 'auto'];
            }
        }

        $started = microtime(true);

        $headers = [
            'x-api-key'         => $apiKey,
            'anthropic-version' => $config['anthropic_version'] ?? self::DEFAULT_VERSION,
        ];

        // Solo hace falta con API keys "de organización" (no scopeadas a un
        // workspace) — Anthropic las rechaza con 400 sin este header. Una key
        // scopeada a un workspace (lo normal al crearla desde la consola
        // dentro de un workspace específico) no lo necesita.
        if (!empty($config['anthropic_workspace_id'])) {
            $headers['anthropic-workspace-id'] = $config['anthropic_workspace_id'];
        }

        try {
            $response = Http::withHeaders($headers)
                ->connectTimeout(10)
                ->timeout($payload['timeout'] ?? 150)
                ->post("{$baseUrl}/v1/messages", $body);

            $durationMs = (int) round((microtime(true) - $started) * 1000);

            return new ConnectorResponse(
                successful: $response->successful(),
                statusCode: $response->status(),
                headers: $response->headers(),
                body: $response->json() ?? $response->body(),
                rawBody: $response->body(),
                durationMs: $durationMs,
            );
        }
        catch (Throwable $e) {
            return ConnectorResponse::fromError($e->getMessage(), (int) round((microtime(true) - $started) * 1000));
        }
    }
}
