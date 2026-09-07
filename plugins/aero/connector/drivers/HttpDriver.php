<?php namespace Aero\Connector\Drivers;

use Http;
use Throwable;
use Aero\Connector\Classes\AuthBuilder;
use Aero\Connector\Classes\ConnectorResponse;
use Aero\Connector\Contracts\ConnectorDriver;
use Aero\Connector\Models\Connector;

/**
 * REST genérico: método/headers/body configurables por el propio Connector.
 * Sirve también como "webhook saliente" simple (Slack/Discord: solo una URL
 * que recibe un POST JSON) — basta con dejar el body template vacío y mandar
 * el payload directo.
 */
class HttpDriver implements ConnectorDriver
{
    public function send(Connector $connector, array $payload = []): ConnectorResponse
    {
        return $this->request($connector, $payload);
    }

    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        $config = (array) $connector->config;
        $payload = $overridePayload ?: ($config['sample_payload'] ?? []);

        return $this->request($connector, $payload);
    }

    protected function request(Connector $connector, array $payload): ConnectorResponse
    {
        $config = (array) $connector->config;
        $method = strtoupper($config['method'] ?? 'POST');
        $headers = (array) ($config['headers'] ?? []);
        $query = [];

        [$headers, $query] = AuthBuilder::apply($connector, $headers, $query);
        $baseUrl = $connector->resolvedBaseUrl();

        $started = microtime(true);

        try {
            $request = Http::withHeaders($headers)->timeout(20);

            $response = match ($method) {
                'GET'    => $request->get($baseUrl, array_merge($query, $payload)),
                'DELETE' => $request->delete($baseUrl, $payload),
                'PUT'    => $request->put($baseUrl, $payload),
                'PATCH'  => $request->patch($baseUrl, $payload),
                default  => $request->post($baseUrl, $payload),
            };

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
