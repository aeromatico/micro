<?php namespace Aero\WpFlash\Classes;

use Aero\Sites\Models\Tenant;
use Aero\WpFlash\Drivers\CloudflareDriver;
use Aero\WpFlash\Models\Settings;

/**
 * Apunta (o desapunta) el subdominio de un tenant hacia el WordPress
 * Multisite, a nivel DNS — no a nivel de aplicación. Un CNAME creado en
 * Cloudflare es más simple y rápido que un proxy/redirect en nuestra propia
 * capa HTTP, y es justo lo que la zona `market.com.bo` (dada por el usuario)
 * permite automatizar.
 *
 * Solo funciona para subdominios de nuestra propia zona (RootDomain de la
 * plataforma). Un dominio externo del tenant no se puede tocar desde acá: el
 * tenant debe apuntarlo manualmente (instrucción mostrada en el backend).
 */
class DomainRouter
{
    public function pointToWordPress(Tenant $tenant): array
    {
        $connector = Settings::cloudflareConnector();
        if (!$connector) {
            return ['ok' => false, 'message' => 'No hay un Connector de Cloudflare configurado en Ajustes → WpFlash.'];
        }

        if (!$this->isUnderOurZone($tenant)) {
            return [
                'ok'      => false,
                'manual'  => true,
                'message' => 'El dominio de este tenant no pertenece a nuestra zona de Cloudflare: debe apuntarlo manualmente al WordPress Multisite.',
            ];
        }

        $response = app(CloudflareDriver::class)->upsertDnsRecord($connector, $tenant->handle, Settings::wordpressTarget());

        return ['ok' => $response->successful, 'message' => $response->error ?? null];
    }

    public function pointToPlatform(Tenant $tenant): array
    {
        $connector = Settings::cloudflareConnector();
        if (!$connector || !$this->isUnderOurZone($tenant)) {
            return ['ok' => true];
        }

        $response = app(CloudflareDriver::class)->deleteDnsRecord($connector, $tenant->handle);

        return ['ok' => $response->successful, 'message' => $response->error ?? null];
    }

    protected function isUnderOurZone(Tenant $tenant): bool
    {
        // El caso normal: el tenant vive en un subdominio de nuestro propio
        // RootDomain (market.com.bo). Un dominio propio del tenant no tiene
        // rootDomain vinculado de este modo — se trata como "externo".
        return (bool) $tenant->root_domain_id;
    }
}
