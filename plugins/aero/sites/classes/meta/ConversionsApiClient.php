<?php namespace Aero\Sites\Classes\Meta;

use Http;
use Log;

/**
 * Cliente mínimo para la Conversions API (CAPI) de Meta — complementa al
 * Pixel del navegador (themes/master/layouts/base.htm) enviando el mismo
 * evento desde el servidor, con `event_id` compartido para que Meta
 * deduplique. Pensado solo para el embudo propio de la plataforma (alta de
 * tenants, compra de créditos, regalos) — nunca para pedidos de las tiendas
 * de los tenants.
 *
 * Fuente de verdad de credenciales: config('services.meta_pixel') / .env
 * (META_PIXEL_ID, META_CAPI_ACCESS_TOKEN). Sin esas dos, isConfigured()
 * devuelve false y send() no hace nada (no hay pixel de prueba que rompa).
 */
class ConversionsApiClient
{
    public static function isConfigured(): bool
    {
        return filled(config('services.meta_pixel.pixel_id'))
            && filled(config('services.meta_pixel.access_token'));
    }

    /**
     * Snapshot de señales de atribución disponibles en la request actual
     * (cookies del Pixel, IP, user agent). Se captura en el momento del
     * checkout (creación del QR) para poder usarlo más tarde, cuando el
     * webhook del banco confirma el pago fuera de cualquier request de
     * navegador.
     */
    public static function captureRequestTracking(?string $externalId = null): array
    {
        $request = request();

        return array_filter([
            'fbp'         => $request?->cookie('_fbp'),
            'fbc'         => $request?->cookie('_fbc'),
            'ip'          => $request?->ip(),
            'user_agent'  => $request?->userAgent(),
            'external_id' => $externalId,
            'url'         => $request?->fullUrl(),
        ], fn ($value) => filled($value));
    }

    /**
     * Envía un evento a la CAPI. Nunca lanza excepción hacia afuera: un
     * fallo de Meta no debe romper un alta, un pago o un regalo.
     *
     * @param array $tracking  lo devuelto por captureRequestTracking() (o guardado antes).
     * @param array $userData  claves sin hashear: email, phone, first_name, country.
     * @param array $customData  ej. ['value' => 199, 'currency' => 'BOB'].
     */
    public static function send(
        string $eventName,
        string $eventId,
        array $tracking = [],
        array $userData = [],
        array $customData = []
    ): bool {
        if (!static::isConfigured()) {
            return false;
        }

        $ud = [];

        if (filled($tracking['fbp'] ?? null)) {
            $ud['fbp'] = $tracking['fbp'];
        }
        if (filled($tracking['fbc'] ?? null)) {
            $ud['fbc'] = $tracking['fbc'];
        }
        if (filled($tracking['ip'] ?? null)) {
            $ud['client_ip_address'] = $tracking['ip'];
        }
        if (filled($tracking['user_agent'] ?? null)) {
            $ud['client_user_agent'] = $tracking['user_agent'];
        }
        if (filled($tracking['external_id'] ?? null)) {
            $ud['external_id'] = static::hash((string) $tracking['external_id']);
        }
        if (filled($userData['email'] ?? null)) {
            $ud['em'] = [static::hash(strtolower(trim($userData['email'])))];
        }
        if (filled($userData['phone'] ?? null)) {
            $ud['ph'] = [static::hash(static::normalizePhone($userData['phone']))];
        }
        if (filled($userData['first_name'] ?? null)) {
            $ud['fn'] = [static::hash(strtolower(trim($userData['first_name'])))];
        }
        if (filled($userData['country'] ?? null)) {
            $ud['country'] = [static::hash(strtolower(trim($userData['country'])))];
        }

        if ($ud === []) {
            // Sin ninguna señal de coincidencia (ni cookies del Pixel ni
            // PII), Meta no puede atribuir el evento a nadie — no vale la
            // pena la llamada.
            return false;
        }

        $event = array_filter([
            'event_name'       => $eventName,
            'event_time'       => now()->timestamp,
            'event_id'         => $eventId,
            'action_source'    => 'website',
            'event_source_url' => $tracking['url'] ?? null,
            'user_data'        => $ud,
            'custom_data'      => $customData ?: null,
        ], fn ($value) => $value !== null);

        $pixelId = config('services.meta_pixel.pixel_id');
        $version = config('services.meta_pixel.api_version', 'v21.0');

        $fields = [
            'data'         => json_encode([$event]),
            'access_token' => config('services.meta_pixel.access_token'),
        ];

        if (filled(config('services.meta_pixel.test_event_code'))) {
            $fields['test_event_code'] = config('services.meta_pixel.test_event_code');
        }

        try {
            $response = Http::asForm()
                ->timeout(4)
                ->post("https://graph.facebook.com/{$version}/{$pixelId}/events", $fields);

            if (!$response->successful()) {
                Log::warning('Aero.Sites Meta CAPI: evento rechazado', [
                    'event'  => $eventName,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error("Aero.Sites Meta CAPI: fallo enviando evento {$eventName}: " . $e->getMessage());

            return false;
        }
    }

    public static function hash(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : hash('sha256', $value);
    }

    /**
     * Bolivia-first: si el número no trae código de país, asume 591. No
     * intenta validar celulares extranjeros más allá de dejar solo dígitos.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits !== '' && !str_starts_with($digits, '591') && strlen($digits) <= 9) {
            $digits = '591' . $digits;
        }

        return $digits;
    }
}
