<?php namespace Aero\Connector\Drivers;

use Http;
use Throwable;
use Aero\Connector\Classes\ConnectorResponse;
use Aero\Connector\Contracts\ConnectorDriver;
use Aero\Connector\Models\Connector;

/**
 * Bot API de Telegram: el token va en la URL (no en un header), a diferencia
 * de los demás drivers — por eso no usa AuthBuilder. `credentials.api_key`
 * guarda el token del bot (creado con @BotFather).
 */
class TelegramDriver implements ConnectorDriver
{
    /** payload: chat_id, text, reply_to_message_id? (para responder en hilo). */
    public function send(Connector $connector, array $payload = []): ConnectorResponse
    {
        return $this->call($connector, 'sendMessage', array_filter([
            'chat_id'              => $payload['chat_id'] ?? null,
            'text'                 => $payload['text'] ?? '',
            'reply_to_message_id'  => $payload['reply_to_message_id'] ?? null,
            'parse_mode'           => $payload['parse_mode'] ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /** Sin chat_id: solo valida el token (getMe). Con chat_id: manda un mensaje real de prueba. */
    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        if (!empty($overridePayload['chat_id'])) {
            return $this->send($connector, $overridePayload + [
                'text' => $overridePayload['text'] ?? 'Conexión de prueba desde el panel de Livechat ✅',
            ]);
        }

        return $this->call($connector, 'getMe', [], 'GET');
    }

    /** Últimas entregas pendientes del bot (solo sirve mientras NO tenga webhook activo). */
    public function getUpdates(Connector $connector): ConnectorResponse
    {
        return $this->call($connector, 'getUpdates', [], 'GET');
    }

    /** Registra la URL pública del WebhookEndpoint como webhook del bot. */
    public function setWebhook(Connector $connector, string $url, ?string $secretToken): ConnectorResponse
    {
        return $this->call($connector, 'setWebhook', array_filter([
            'url'             => $url,
            'secret_token'    => $secretToken,
            'allowed_updates' => json_encode(['message']),
        ], fn ($v) => $v !== null && $v !== ''));
    }

    protected function call(Connector $connector, string $method, array $body, string $httpMethod = 'POST'): ConnectorResponse
    {
        $token = $connector->credentials['api_key'] ?? null;

        if (!$token) {
            return ConnectorResponse::fromError('Falta el token del bot (campo "API Key").');
        }

        $base = rtrim($connector->resolvedBaseUrl() ?: 'https://api.telegram.org', '/');
        $url = "{$base}/bot{$token}/{$method}";

        $started = microtime(true);

        try {
            $response = $httpMethod === 'GET'
                ? Http::timeout(15)->get($url)
                : Http::asJson()->timeout(15)->post($url, $body);

            $durationMs = (int) round((microtime(true) - $started) * 1000);
            $json = $response->json();

            return new ConnectorResponse(
                successful: $response->successful() && (bool) ($json['ok'] ?? false),
                statusCode: $response->status(),
                headers: $response->headers(),
                body: $json ?? $response->body(),
                rawBody: $response->body(),
                durationMs: $durationMs,
                error: ($json['ok'] ?? false) ? null : ($json['description'] ?? 'Error desconocido de Telegram.'),
            );
        }
        catch (Throwable $e) {
            return ConnectorResponse::fromError($e->getMessage(), (int) round((microtime(true) - $started) * 1000));
        }
    }
}
