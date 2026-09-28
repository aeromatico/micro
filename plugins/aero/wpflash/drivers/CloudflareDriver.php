<?php namespace Aero\WpFlash\Drivers;

use Http;
use Throwable;
use Aero\Connector\Classes\ConnectorResponse;
use Aero\Connector\Contracts\ConnectorDriver;
use Aero\Connector\Models\Connector;

/**
 * API de Cloudflare (auth "legacy" por Email+Key, no Bearer/Basic) — se
 * guarda el email en `credentials.api_key` y la Global API Key en
 * `credentials.secret` (los dos campos genéricos del form de Connector) y acá
 * se arman los headers propios `X-Auth-Email`/`X-Auth-Key`, igual que
 * AiAnthropicDriver arma los suyos en vez de usar AuthBuilder.
 *
 * Un solo Connector de este tipo, a nivel plataforma (owner_type null),
 * apuntando a la zona de market.com.bo — ver DomainRouter.
 */
class CloudflareDriver implements ConnectorDriver
{
    public function send(Connector $connector, array $payload = []): ConnectorResponse
    {
        $method = strtoupper($payload['method'] ?? 'GET');
        $path = $payload['path'] ?? '/user/tokens/verify';

        return $this->request($connector, $method, $path, $payload['body'] ?? []);
    }

    public function test(Connector $connector, array $overridePayload = []): ConnectorResponse
    {
        $zoneId = $connector->config['zone_id'] ?? null;

        return $zoneId
            ? $this->request($connector, 'GET', "/zones/{$zoneId}", [])
            : $this->request($connector, 'GET', '/user/tokens/verify', []);
    }

    /**
     * Crea o actualiza el CNAME `{subdomain}.market.com.bo` → `$target`
     * (ej. `wp.market.com.bo`). Solo tiene sentido para subdominios de
     * nuestra propia zona — un dominio externo del tenant no se puede tocar
     * desde acá (ver DomainRouter::pointToWordPress).
     */
    public function upsertDnsRecord(Connector $connector, string $subdomain, string $target): ConnectorResponse
    {
        $zoneId = $connector->config['zone_id'] ?? null;
        if (!$zoneId) {
            return ConnectorResponse::fromError('El Connector de Cloudflare no tiene zone_id configurado.');
        }

        $lookup = $this->request($connector, 'GET', "/zones/{$zoneId}/dns_records", [], ['name' => $subdomain]);
        $existingId = $lookup->body['result'][0]['id'] ?? null;

        $body = ['type' => 'CNAME', 'name' => $subdomain, 'content' => $target, 'proxied' => true, 'ttl' => 1];

        return $existingId
            ? $this->request($connector, 'PUT', "/zones/{$zoneId}/dns_records/{$existingId}", $body)
            : $this->request($connector, 'POST', "/zones/{$zoneId}/dns_records", $body);
    }

    public function deleteDnsRecord(Connector $connector, string $subdomain): ConnectorResponse
    {
        $zoneId = $connector->config['zone_id'] ?? null;
        if (!$zoneId) {
            return ConnectorResponse::fromError('El Connector de Cloudflare no tiene zone_id configurado.');
        }

        $lookup = $this->request($connector, 'GET', "/zones/{$zoneId}/dns_records", [], ['name' => $subdomain]);
        $existingId = $lookup->body['result'][0]['id'] ?? null;

        if (!$existingId) {
            return new ConnectorResponse(successful: true, statusCode: 200, headers: [], body: null, rawBody: '', durationMs: 0);
        }

        return $this->request($connector, 'DELETE', "/zones/{$zoneId}/dns_records/{$existingId}", []);
    }

    protected function request(Connector $connector, string $method, string $path, array $body = [], array $query = []): ConnectorResponse
    {
        $headers = [
            'X-Auth-Email' => $connector->credentials['api_key'] ?? null,
            'X-Auth-Key'   => $connector->credentials['secret'] ?? null,
            'Content-Type' => 'application/json',
        ];

        $baseUrl = rtrim((string) $connector->resolvedBaseUrl(), '/') . $path;
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
