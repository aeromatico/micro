<?php namespace Aero\Wapi\Models;

use Aero\Wapi\Classes\WapiClient;
use Flash;
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

    /** Días que wapi conserva los mensajes guardados (por defecto 30). */
    public static function getRetentionDays(): int
    {
        $days = (int) self::get('chat_retention_days', 30);

        return $days >= 1 && $days <= 3650 ? $days : 30;
    }

    /** Guardar también fotos/audios/documentos en disco (por defecto no). */
    public static function storesMedia(): bool
    {
        return (bool) self::get('store_media', false);
    }

    /**
     * La retención y la multimedia las aplica wapi (es quien guarda los chats), así que
     * al guardar esta pantalla se le envían. Sin API key no hay a quién enviarlas.
     */
    public function afterSave()
    {
        if (!self::isConfigured()) {
            return;
        }

        try {
            (new WapiClient())->put('/account/settings', [
                'chatRetentionDays' => self::getRetentionDays(),
                'storeMedia'        => self::storesMedia(),
            ]);
        } catch (\Throwable $e) {
            Flash::warning(trans('aero.wapi::lang.settings.push_failed', ['error' => $e->getMessage()]));
        }
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
