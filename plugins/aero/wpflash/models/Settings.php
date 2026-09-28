<?php namespace Aero\WpFlash\Models;

use Model;

/**
 * WordPress vive en este mismo servidor (`/www/wwwroot/wp.market.com.bo`),
 * así que el provisioning no habla por HTTP contra un mu-plugin: corre
 * WP-CLI directo (ver Classes\WpCli). Estos valores son rutas/binarios del
 * servidor, no secretos — está bien que vivan en SettingsModel.
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_wpflash_settings';

    public $settingsFields = 'fields.yaml';

    public static function wpPath(): string
    {
        return rtrim(self::get('wp_path', '/www/wwwroot/wp.market.com.bo'), '/');
    }

    public static function wpCliBinary(): string
    {
        return self::get('wp_cli_binary', '/usr/local/bin/wp');
    }

    public static function phpBinary(): string
    {
        return self::get('php_binary', '/www/server/php/84/bin/php');
    }

    public static function wpNetworkDomain(): string
    {
        return self::get('wp_network_domain', 'wp.market.com.bo');
    }
}
