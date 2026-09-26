<?php namespace Aero\Hub\Http\Controllers\Api;

use Aero\Connector\Classes\ConnectorClient;
use Aero\Connector\Models\Connector;
use Aero\Hub\Classes\CatalogSync;
use Aero\Hub\Classes\HubCredits;
use Aero\Hub\Models\HubEndpoint;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Str;

/**
 * Reenvía una petición 1:1 hacia YepAPI, con el mismo envelope de respuesta
 * ({"ok":true,"data":...} / {"ok":false,"error":{...}}) para que la doc
 * pública de YepAPI siga describiendo exactamente lo que este endpoint hace,
 * solo cambiando el dominio.
 */
class ProxyController extends Controller
{
    public function handle(Request $request, string $path)
    {
        $endpoint = HubEndpoint::where('path', '/' . ltrim($path, '/'))
            ->where('method', $request->method())
            ->where('is_active', true)
            ->first();

        if (!$endpoint) {
            return $this->envelopeError('endpoint_not_found', 'Este endpoint no existe o todavía no fue activado.', 404);
        }

        $apiKey = $request->attributes->get('api_key');
        $scope = CatalogSync::scopeFor($endpoint->category);

        if (!$apiKey || !$apiKey->hasScope($scope)) {
            return $this->envelopeError('insufficient_scope', "Esta API key no tiene el permiso '{$scope}'.", 403);
        }

        if (!HubCredits::isBillable($endpoint)) {
            return $this->envelopeError('endpoint_not_configured', 'Este endpoint no tiene un precio configurado todavía.', 503);
        }

        $tenantId = ($apiKey->owner_type === \Aero\Sites\Models\Tenant::class) ? (int) $apiKey->owner_id : null;

        if (!$tenantId) {
            return $this->envelopeError('tenant_required', 'Esta API key no está asociada a un tenant con créditos.', 403);
        }

        $body = $this->buildUpstreamBody($request, $endpoint);

        // Job asíncrono: se cobra un hold al encolar, se liquida cuando el
        // polling de /v1/media/status/{jobId} reporte completed/failed.
        if ($endpoint->is_async && str_contains($endpoint->path, '/media/queue')) {
            return $this->proxyMediaQueue($endpoint, $tenantId, $body);
        }

        if ($endpoint->is_async && str_contains($endpoint->path, '/media/status')) {
            return $this->proxyMediaStatus($request, $endpoint, $body);
        }

        try {
            $response = HubCredits::attempt($tenantId, $endpoint, function () use ($endpoint, $body) {
                $upstream = $this->call($endpoint, $body);

                // Sin esto, un 4xx/5xx de YepAPI (respuesta normal, sin
                // excepción) queda cobrado igual: attempt() solo reembolsa
                // si el callback truena.
                if (!$upstream->successful) {
                    throw new \Aero\Hub\Classes\UpstreamFailedException($upstream);
                }

                return $upstream;
            });
        }
        catch (\Aero\Credits\Classes\Exceptions\InsufficientCreditsException $e) {
            return $this->envelopeError('insufficient_credits', $e->getMessage(), 402);
        }
        catch (\Aero\Hub\Classes\UpstreamFailedException $e) {
            return $this->forward($e->response);
        }

        if ($endpoint->pricing_type === 'per_volume' && $response->successful) {
            HubCredits::chargeOverage($tenantId, $endpoint, $this->countUnits($response->body, $endpoint->count_path));
        }

        return $this->forward($response);
    }

    /**
     * `stream: true` se fuerza a `false`: v1 no soporta passthrough SSE, y
     * silenciosamente devolver el JSON completo mantiene el body/response
     * compatible con un cliente que espere el envelope habitual.
     */
    protected function buildUpstreamBody(Request $request, HubEndpoint $endpoint): array
    {
        $body = $request->all();

        if ($endpoint->is_streaming && array_key_exists('stream', $body)) {
            $body['stream'] = false;
        }

        return $body;
    }

    protected function call(HubEndpoint $endpoint, array $body)
    {
        $connector = Connector::where('type', 'yepapi')->first();

        if (!$connector) {
            throw new \RuntimeException('Aero.Hub: no existe el Connector de tipo "yepapi" (ver updates/seed_yepapi_connector.php).');
        }

        return app(ConnectorClient::class)->send($connector, [
            '_path'   => $endpoint->path,
            '_method' => $endpoint->method,
            '_body'   => $body,
        ]);
    }

    protected function countUnits($body, ?string $countPath): int
    {
        if (!$countPath || !is_array($body)) {
            return 0;
        }

        $value = $body;
        foreach (explode('.', $countPath) as $segment) {
            $value = $value[$segment] ?? null;
        }

        return is_array($value) ? count($value) : 0;
    }

    protected function proxyMediaQueue(HubEndpoint $endpoint, int $tenantId, array $body)
    {
        try {
            $response = $this->call($endpoint, $body);
        }
        catch (\Throwable $e) {
            return $this->envelopeError('upstream_error', $e->getMessage(), 502);
        }

        if ($response->successful && !empty($response->body['data']['jobId'])) {
            HubCredits::openMediaHold($tenantId, $endpoint, (string) $response->body['data']['jobId']);
        }

        return $this->forward($response);
    }

    protected function proxyMediaStatus(Request $request, HubEndpoint $endpoint, array $body)
    {
        $jobId = (string) $request->route('path');
        $jobId = Str::afterLast($jobId, '/');

        try {
            $response = $this->call($endpoint, $body);
        }
        catch (\Throwable $e) {
            return $this->envelopeError('upstream_error', $e->getMessage(), 502);
        }

        $status = $response->body['data']['status'] ?? null;

        if ($status === 'completed') {
            HubCredits::settleMediaJob($jobId);
        }
        elseif ($status === 'failed') {
            HubCredits::refundMediaJob($jobId, 'Job de media falló en YepAPI');
        }

        return $this->forward($response);
    }

    protected function forward($response)
    {
        return response()->json($response->body, $response->statusCode ?: 502);
    }

    protected function envelopeError(string $code, string $message, int $status)
    {
        return response()->json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
    }
}
