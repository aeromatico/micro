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

        $started = microtime(true);

        try {
            $request = Http::withHeaders($headers)->timeout(20);

            $response = match ($method) {
                'GET'    => $request->get($connector->base_url, array_merge($query, $payload)),
                'DELETE' => $request->delete($connector->base_url, $payload),
                'PUT'    => $request->put($connector->base_url, $payload),
                'PATCH'  => $request->patch($connector->base_url, $payload),
                default  => $request->post($connector->base_url, $payload),
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
