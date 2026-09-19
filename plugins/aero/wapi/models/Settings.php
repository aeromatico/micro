<?php namespace Aero\Wapi\Models;

use Model;

/**
 * Configuración global del plugin: credenciales de la instancia de wapi que
 * corre en este mismo servidor. A diferencia de Aero.Hello (Zernio), acá no
 * hay override por perfil todavía — una sola instalación de wapi sirve a
 * todos los tenants con una API key por cuenta creada desde su propio CLI
 * (`npm run cli:init`), así que basta con guardar esa key acá.
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_wapi_settings';

    public $settingsFields = 'fields.yaml';

    public static function getBaseUrl(): string
    {
        return rtrim((string) self::get('base_url', 'https://wapi.clouds.com.bo/v1'), '/');
    }

    public static function getApiKey(): ?string
    {
        return self::get('api_key');
    }

    public static function getWebhookSecret(): ?string
    {
        return self::get('webhook_secret');
    }

    public static function allowsUnsignedWebhooks(): bool
    {
        return (bool) self::get('allow_unsigned_webhooks', false);
    }

    public static function isConfigured(): bool
    {
        return !empty(self::getApiKey());
    }

    /**
     * URL a registrar en wapi (`POST /v1/webhooks`) para el `instanceId` de
     * cada cuenta conectada.
     */
    public static function webhookUrl(string $instanceId): string
    {
        return url('/api/v1/wapi/webhooks/' . $instanceId);
    }
}
