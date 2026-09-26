<?php namespace Aero\Hub\Classes\Connector;

use Aero\Connector\Classes\ConnectorResponse;
use Aero\Connector\Contracts\ConnectorDriver;
use Aero\Connector\Models\Connector;
use Http;
use Throwable;

/**
 * Driver de un único Connector ("YepAPI") reutilizado por las 155 rutas del
 * proxy: a diferencia de HttpDriver (1 endpoint fijo por conector), este lee
 * el path/método reales del `$payload` — mismo criterio que TelegramDriver
 * enrutando por nombre de método — porque Aero.Hub necesita 155 endpoints
 * distintos detrás de una sola credencial `x-api-key`.
 *
 * Convención del payload: `_path` (ej. "/v1/ai/chat"), `_method` (GET/POST/...),
 * `_body` (array, el body/query real a reenviar).
 */
class YepApiDriver implements ConnectorDriver
{
    public function send(Connector $connector, array $payload = []): ConnectorResponse
    {
        return $this->request($connector, $payload);
    }

    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        $payload = $overridePayload ?: ['_path' => '/v1/ai/models', '_method' => 'GET', '_body' => []];

        return $this->request($connector, $payload);
    }

    protected function request(Connector $connector, array $payload): ConnectorResponse
    {
        $path = $payload['_path'] ?? '';
        $method = strtoupper($payload['_method'] ?? 'POST');
        $body = (array) ($payload['_body'] ?? []);
        $apiKey = $connector->api_key;

        $url = rtrim((string) $connector->resolvedBaseUrl(), '/') . $path;
        $started = microtime(true);

        try {
            $request = Http::withHeaders(['x-api-key' => $apiKey])->timeout(60);

            $response = $method === 'GET'
                ? $request->get($url, $body)
                : $request->send($method, $url, ['json' => $body]);

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
