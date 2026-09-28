<?php namespace Aero\WpFlash\Classes;

use Log;
use Aero\WpFlash\Models\SiteInstance;

/**
 * Puente entre el WebhookEndpoint compartido de Aero.Connector (uno por
 * tópico, no por tenant) y el sync real. El WebhookEndpoint queda con
 * verification=none (el verificador genérico de Connector espera HMAC
 * hex/GitHub-style; WooCommerce firma en base64 y sin prefijo) — la
 * verificación de verdad pasa acá, contra el secreto DE ESE tenant
 * (resuelto primero por el sitio de origen, ver Provisioner::registerWebhooks).
 */
class EventListeners
{
    public static function handleProductWebhook($payload, $request): void
    {
        static::dispatch($payload, $request, ProductSync::class);
    }

    public static function handleCustomerWebhook($payload, $request): void
    {
        static::dispatch($payload, $request, CustomerSync::class);
    }

    protected static function dispatch(array $payload, $request, string $syncClass): void
    {
        $source = rtrim((string) $request->header('X-WC-Webhook-Source'), '/');
        $topic  = (string) $request->header('X-WC-Webhook-Topic', '');

        if (!$source) {
            Log::warning('WpFlash: webhook sin X-WC-Webhook-Source, descartado.');
            return;
        }

        $site = SiteInstance::where('wp_admin_url', 'like', $source . '%')->first();

        if (!$site || !$site->connector) {
            Log::warning("WpFlash: no se encontró un sitio activo para el origen {$source}.");
            return;
        }

        $secret = $site->connector->credentials['webhook_secret'] ?? null;
        $signature = $request->header('X-WC-Webhook-Signature');
        $expected = $secret ? base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true)) : null;

        if (!$secret || !$signature || !hash_equals($expected, $signature)) {
            Log::warning("WpFlash: firma inválida en webhook de {$source} (tenant {$site->tenant_id}).");
            return;
        }

        $syncClass::handle($site->tenant_id, $topic, $payload);

        $site->last_synced_at = now();
        $site->saveQuietly();
    }
}
