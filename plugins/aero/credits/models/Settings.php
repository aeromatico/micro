<?php namespace Aero\Credits\Models;

use Model;

/**
 * Ajustes globales del sistema de créditos. El valor USD por crédito vive
 * por color en CreditType.usd_value, no acá — esto es solo la política de
 * bloqueo.
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'aero_credits_settings';

    public $settingsFields = 'fields.yaml';

    public static function blocksOnInsufficientBalance(): bool
    {
        return (bool) self::get('block_on_insufficient_balance', true);
    }
}
