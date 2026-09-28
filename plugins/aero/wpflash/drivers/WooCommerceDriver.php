<?php namespace Aero\WpFlash\Drivers;

use Http;
use Throwable;
use Aero\Connector\Classes\AuthBuilder;
use Aero\Connector\Classes\ConnectorResponse;
use Aero\Connector\Contracts\ConnectorDriver;
use Aero\Connector\Models\Connector;

/**
 * WooCommerce REST API v3 de un childsite. La autenticación (consumer_key +
 * consumer_secret) ya encaja tal cual con AuthBuilder::apply() genérico
 * (api_key + secret → HTTP Basic, que es justo lo que WooCommerce espera
 * sobre HTTPS) — no hace falta reinventar el auth, solo fijar el path base.
 *
 * `send()`/`test()` cubren el contrato genérico de ConnectorDriver; los
 * métodos propios (listProducts, listCustomers, registerWebhook) son los que
 * realmente usan Classes\Provisioner y Console\SyncCommand — igual que
 * TelegramDriver agrega métodos propios más allá de la interfaz.
 */
class WooCommerceDriver implements ConnectorDriver
{
    public function send(Connector $connector, array $payload = []): ConnectorResponse
    {
        $method = strtoupper($payload['method'] ?? 'GET');
        $endpoint = $payload['endpoint'] ?? '/products';

        return $this->request($connector, $method, $endpoint, $payload['params'] ?? [], $payload['body'] ?? []);
    }

    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        return $this->request($connector, 'GET', '/system_status', [], []);
    }

    public function listProducts(Connector $connector, array $params = []): ConnectorResponse
    {
        return $this->request($connector, 'GET', '/products', $params, []);
    }

    public function listCustomers(Connector $connector, array $params = []): ConnectorResponse
    {
        return $this->request($connector, 'GET', '/customers', $params, []);
    }

    public function getProduct(Connector $connector, int|string $wpProductId): ConnectorResponse
    {
        return $this->request($connector, 'GET', "/products/{$wpProductId}", [], []);
    }

    /**
     * Registra un webhook de WooCommerce en el childsite (idempotente en la
     * práctica: llamarlo dos veces solo crea una entrada duplicada en WP, sin
     * romper nada — el Provisioner lo llama una sola vez por tópico).
     */
    public function registerWebhook(Connector $connector, string $topic, string $deliveryUrl, string $secret): ConnectorResponse
    {
        return $this->request($connector, 'POST', '/webhooks', [], [
            'name'         => "wpflash-{$topic}",
            'topic'        => $topic,
            'delivery_url' => $deliveryUrl,
            'secret'       => $secret,
            'status'       => 'active',
        ]);
    }

    protected function request(Connector $connector, string $method, string $endpoint, array $params, array $body): ConnectorResponse
    {
        [$headers, $query] = AuthBuilder::apply($connector, [], $params);
        $baseUrl = rtrim((string) $connector->resolvedBaseUrl(), '/') . '/wp-json/wc/v3' . $endpoint;

        $started = microtime(true);

        try {
            $request = Http::withHeaders($headers)->timeout(20);

            $response = match ($method) {
                'GET'    => $request->get($baseUrl, $query),
                'DELETE' => $request->delete($baseUrl, $body),
                'PUT'    => $request->put($baseUrl, $body),
                'PATCH'  => $request->patch($baseUrl, $body),
                default  => $request->post($baseUrl, $body),
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
