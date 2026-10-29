<?php namespace Aero\Viral\Classes\Kyc;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Cliente mínimo de Didit (v3). Solo desde el backend: la API key es secreta. */
class DiditClient
{
    public static function isConfigured(): bool
    {
        return (bool) (config('services.didit.api_key') && config('services.didit.workflow_id'));
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.didit.base_url'), '/'))
            ->withHeaders(['x-api-key' => (string) config('services.didit.api_key')])
            ->acceptJson()->asJson()
            ->connectTimeout(5)->timeout(25);
    }

    /**
     * Crea (o reutiliza, es idempotente por workflow+vendor_data) una sesión de verificación.
     * @return array{session_id:string,url:string,status:string}
     */
    public function createSession(string $vendorData, string $callbackUrl, array $metadata = []): array
    {
        $r = $this->http()->post('/v3/session/', array_filter([
            'workflow_id' => config('services.didit.workflow_id'),
            'vendor_data' => $vendorData,
            'callback' => $callbackUrl,
            'callback_method' => 'both',
            'language' => 'es',
            'metadata' => $metadata ?: null,
        ]));

        if (!$r->successful() || !$r->json('session_id') || !$r->json('url')) {
            throw new KycException('No pudimos iniciar la verificación (' . $r->status() . '). ' . mb_substr((string) ($r->json('detail') ?? ''), 0, 160));
        }

        return ['session_id' => $r->json('session_id'), 'url' => $r->json('url'), 'status' => (string) $r->json('status')];
    }

    /** Decisión completa de una sesión (para datos del documento). */
    public function decision(string $sessionId): array
    {
        $r = $this->http()->get('/v3/session/' . rawurlencode($sessionId) . '/decision/');

        return $r->successful() ? (array) $r->json() : [];
    }
}
