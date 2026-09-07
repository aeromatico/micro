<?php namespace Aero\Connector\Drivers;

use Http;
use Throwable;
use Aero\Connector\Classes\ConnectorResponse;
use Aero\Connector\Contracts\ConnectorDriver;
use Aero\Connector\Models\Connector;

/**
 * Cualquier proveedor con API compatible con OpenAI Chat Completions:
 * OpenAI, OpenRouter, GLM, LM Studio/Ollama expuestos como OpenAI-compatible, etc.
 * `base_url` es la raíz de la API (ej. https://api.openai.com/v1); se le
 * agrega `/chat/completions`.
 */
class AiOpenAiCompatibleDriver implements ConnectorDriver
{
    public function send(Connector $connector, array $payload = []): ConnectorResponse
    {
        $messages = $payload['messages'] ?? [
            ['role' => 'user', 'content' => $payload['prompt'] ?? ''],
        ];

        return $this->chat($connector, $messages, $payload['model'] ?? null);
    }

    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        $messages = $overridePayload['messages'] ?? [
            ['role' => 'user', 'content' => $overridePayload['prompt'] ?? 'Responde solo "ok" si me recibes.'],
        ];

        return $this->chat($connector, $messages, $overridePayload['model'] ?? null);
    }

    /**
     * `$modelOverride` permite que un mismo connector (con un modelo "por
     * defecto" en su `config`) sea usado con distinto modelo por cada
     * consumidor (ej. cada bot de Aero.Chatbots elige el suyo del catálogo).
     */
    protected function chat(Connector $connector, array $messages, ?string $modelOverride = null): ConnectorResponse
    {
        $config = (array) $connector->config;
        $apiKey = $connector->credentials['api_key'] ?? null;
        $model = $modelOverride ?: ($config['model'] ?? 'gpt-4o-mini');
        $baseUrl = rtrim($connector->resolvedBaseUrl() ?: 'https://api.openai.com/v1', '/');

        $started = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post("{$baseUrl}/chat/completions", [
                    'model'    => $model,
                    'messages' => $messages,
                ]);

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
