<?php namespace Aero\WpFlash\Models;

use Model;
use Aero\Connector\Models\Connector;

/**
 * Apunta a los dos Connectors que WP Flash necesita a nivel plataforma (uno
 * solo de cada, no por tenant): el puente de red que crea childsites nuevos
 * (tipo genérico `http` de Aero.Connector, contra el mu-plugin de WordPress —
 * ver README.md) y el de Cloudflare que apunta subdominios al Multisite. Se
 * eligen por dropdown en vez de buscarlos por nombre a mano — evita casos
 * frágiles si alguien renombra el Connector.
 *
 * Solo guarda IDs (no secretos): las credenciales reales viven cifradas en el
 * propio Connector, donde sí les corresponde estar.
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_wpflash_settings';

    public $settingsFields = 'fields.yaml';

    public function getNetworkConnectorIdOptions(): array
    {
        return Connector::where('type', 'http')->pluck('name', 'id')->all();
    }

    public function getCloudflareConnectorIdOptions(): array
    {
        return Connector::where('type', 'cloudflare')->pluck('name', 'id')->all();
    }

    public static function networkConnector(): ?Connector
    {
        return Connector::find(self::get('network_connector_id'));
    }

    public static function cloudflareConnector(): ?Connector
    {
        return Connector::find(self::get('cloudflare_connector_id'));
    }

    public static function wordpressTarget(): string
    {
        return self::get('wordpress_target', 'wp.market.com.bo');
    }
}
