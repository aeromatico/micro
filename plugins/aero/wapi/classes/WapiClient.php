<?php namespace Aero\Wapi\Classes;

use Aero\Wapi\Classes\Exceptions\WapiApiException;
use Aero\Wapi\Models\Settings;
use Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

/**
 * Cliente delgado sobre la REST API de wapi (self-hosted, ver
 * https://github.com/aeromatico/wapi). Una sola instalación de wapi, una key
 * por cuenta creada con su CLI — nada de resolución por perfil como Zernio
 * todavía, ver el docblock de Settings.
 */
class WapiClient
{
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
    ) {
        $this->apiKey = $apiKey ?? Settings::getApiKey();
        $this->baseUrl = rtrim($baseUrl ?? Settings::getBaseUrl(), '/');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    public function get(string $uri, array $query = []): array
    {
        return $this->handle($this->request()->get($uri, $query));
    }

    public function post(string $uri, array $payload = []): array
    {
        return $this->handle($this->request()->post($uri, $payload));
    }

    public function put(string $uri, array $payload = []): array
    {
        return $this->handle($this->request()->put($uri, $payload));
    }

    public function delete(string $uri): array
    {
        return $this->handle($this->request()->delete($uri));
    }

    /**
     * Para GET /instances/:id/qr: wapi devuelve 404 mientras el QR todavía
     * no está listo (instancia recién creada) o ya se conectó — un poll
     * normal del flujo, no un error a propagar como excepción.
     */
    public function getOrNull(string $uri, array $query = []): ?array
    {
        $response = $this->request()->get($uri, $query);

        if ($response->status() === 404) {
            return null;
        }

        return $this->handle($response);
    }

    protected function request(): PendingRequest
    {
        if (!$this->isConfigured()) {
            throw new WapiApiException('La API key de wapi no está configurada (Configuración → wapi).');
        }

        return Http::withHeaders(['X-API-Key' => $this->apiKey])
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson();
    }

    protected function handle(Response $response): array
    {
        if (!$response->successful()) {
            throw WapiApiException::fromResponse($response);
        }

        return (array) ($response->json() ?? []);
    }
}
