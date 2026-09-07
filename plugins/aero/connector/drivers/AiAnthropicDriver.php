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
        $messages = $payload['messages'] ?? [
            ['role' => 'user', 'content' => $payload['prompt'] ?? ''],
        ];

        return $this->messages($connector, $messages, $payload['model'] ?? null);
    }

    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        $messages = $overridePayload['messages'] ?? [
            ['role' => 'user', 'content' => $overridePayload['prompt'] ?? 'Responde solo "ok" si me recibes.'],
        ];

        return $this->messages($connector, $messages, $overridePayload['model'] ?? null);
    }

    protected function messages(Connector $connector, array $messages, ?string $modelOverride = null): ConnectorResponse
    {
        $config = (array) $connector->config;
        $apiKey = $connector->credentials['api_key'] ?? null;
        $model = $modelOverride ?: ($config['model'] ?? 'claude-sonnet-5');
        $baseUrl = rtrim($connector->resolvedBaseUrl() ?: 'https://api.anthropic.com', '/');

        $started = microtime(true);

        try {
            $response = Http::withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => $config['anthropic_version'] ?? self::DEFAULT_VERSION,
                ])
                ->timeout(30)
                ->post("{$baseUrl}/v1/messages", [
                    'model'      => $model,
                    'max_tokens' => $config['max_tokens'] ?? 256,
                    'messages'   => $messages,
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
