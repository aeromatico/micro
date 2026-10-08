<?php namespace Aero\Oauth\Models;

use Model;

/**
 * Solo interruptores. El client_id/client_secret de cada proveedor viven en
 * el .env (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET): SettingsModel no cifra.
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_oauth_settings';

    public $settingsFields = 'fields.yaml';

    public static function googleLoginEnabled(): bool
    {
        return (bool) self::get('google_login_enabled', true);
    }

    public static function linkByVerifiedEmail(): bool
    {
        return (bool) self::get('link_by_verified_email', false);
    }
}
