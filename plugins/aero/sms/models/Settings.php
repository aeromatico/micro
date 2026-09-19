<?php namespace Aero\Sms\Models;

use Model;

/**
 * Credenciales globales del proveedor. Comparten el límite de SettingsModel
 * documentado en Aero\Hello\Models\Settings: quedan en claro dentro del JSON
 * de system_settings, y la protección real es el permiso de la página.
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_sms_settings';

    public $settingsFields = 'fields.yaml';

    public static function driverCode(): string
    {
        return self::get('driver', 'simulated');
    }

    public static function defaultCountryCode(): string
    {
        return preg_replace('/\D+/', '', (string) self::get('default_country_code', '591')) ?: '591';
    }

    public static function maxPerMinute(): int
    {
        return max(1, (int) self::get('max_per_minute', 60));
    }

    public static function maxBatchSize(): int
    {
        return max(1, (int) self::get('max_batch_size', 5000));
    }
}
