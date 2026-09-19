<?php namespace Aero\Wapi\Classes;

use Aero\Wapi\Models\Settings;
use Log;

/**
 * Verifica `X-Webhook-Signature: sha256=<hmac>` (ver
 * server/src/worker/processors/webhook.js::deliverWebhook en el repo de
 * wapi) contra el secreto configurado al registrar el webhook con
 * `POST /v1/webhooks`. Mismo criterio de "falla cerrado sin secreto" que
 * Aero\Hello\Classes\Zernio\WebhookVerifier.
 */
class WapiWebhookVerifier
{
    public function isValid(string $rawBody, ?string $signature): bool
    {
        $secret = Settings::getWebhookSecret();

        if (empty($secret)) {
            if (Settings::allowsUnsignedWebhooks()) {
                Log::warning('aero.wapi: webhook aceptado SIN firma — configura el webhook secret en Configuración → wapi.');
                return true;
            }

            Log::error('aero.wapi: webhook rechazado, no hay webhook secret configurado.');
            return false;
        }

        if (empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        $received = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

        return hash_equals($expected, $received);
    }
}
