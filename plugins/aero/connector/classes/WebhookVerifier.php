<?php namespace Aero\Connector\Classes;

use Aero\Connector\Models\WebhookEndpoint;

/**
 * Verifica una entrega contra la configuración de su WebhookEndpoint.
 * Generaliza el verificador HMAC que aero/hello usa para Zernio, para
 * cualquier proveedor que entregue webhooks.
 */
class WebhookVerifier
{
    public function isValid(WebhookEndpoint $endpoint, string $rawBody, ?string $signature): bool
    {
        return match ($endpoint->verification) {
            'none'         => true,
            'header_token' => $signature !== null && hash_equals((string) $endpoint->secret, $signature),
            'hmac_sha256'  => $this->verifyHmac($endpoint, $rawBody, $signature),
            default        => false,
        };
    }

    protected function verifyHmac(WebhookEndpoint $endpoint, string $rawBody, ?string $signature): bool
    {
        if (empty($endpoint->secret) || empty($signature)) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $endpoint->secret);

        // Meta/GitHub mandan el prefijo `sha256=`; otros proveedores mandan el hex pelado.
        $received = str_starts_with($signature, 'sha256=')
            ? substr($signature, 7)
            : $signature;

        return hash_equals($expected, $received);
    }
}
