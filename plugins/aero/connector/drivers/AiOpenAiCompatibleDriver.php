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
        $payload['messages'] = $payload['messages'] ?? [
            ['role' => 'user', 'content' => $payload['prompt'] ?? ''],
        ];

        return $this->chat($connector, $payload);
    }

    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        $overridePayload['messages'] = $overridePayload['messages'] ?? [
            ['role' => 'user', 'content' => $overridePayload['prompt'] ?? 'Responde solo "ok" si me recibes.'],
        ];

        return $this->chat($connector, $overridePayload);
    }

    /**
     * `$payload['model']` permite que un mismo connector (con un modelo "por
     * defecto" en su `config`) sea usado con distinto modelo por cada
     * consumidor (ej. cada bot de Aero.Chatbots elige el suyo del catálogo).
     *
     * `$payload['tools']`/`$payload['tool_choice']`, si vienen, ya están en
     * el formato function-calling que espera esta API (ver
     * Aero\Chatbots\Classes\AiToolRegistry::toProviderFormat) — este driver
     * solo los reenvía tal cual, no sabe nada de "tools genéricas".
     */
    protected function chat(Connector $connector, array $payload): ConnectorResponse
    {
        $config = (array) $connector->config;
        $apiKey = $connector->credentials['api_key'] ?? null;
        $model = $payload['model'] ?? null;
        $model = $model ?: ($config['model'] ?? 'gpt-4o-mini');
        $baseUrl = rtrim($connector->resolvedBaseUrl() ?: 'https://api.openai.com/v1', '/');

        $body = [
            'model'    => $model,
            'messages' => $payload['messages'],
            // Sin esto, el proveedor aplica su propio default (a veces muy
            // bajo) y corta la respuesta a mitad — ver payload['max_tokens']
            // que arma cada consumidor (ej. SiteGenerator pide JSON largo).
            'max_tokens' => $payload['max_tokens'] ?? $config['max_tokens'] ?? 4096,
        ];

        if (!empty($payload['tools'])) {
            $body['tools'] = $payload['tools'];
            $body['tool_choice'] = $payload['tool_choice'] ?? 'auto';
        }

        $started = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                // 30s por defecto de Laravel se queda corto con modelos
                // "reasoning" (gastan tokens de pensamiento del mismo
                // presupuesto antes de escribir la respuesta) o con
                // respuestas largas — ver payload['timeout'] opcional.
                ->connectTimeout(10)
                ->timeout($payload['timeout'] ?? 150)
                ->post("{$baseUrl}/chat/completions", $body);

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
