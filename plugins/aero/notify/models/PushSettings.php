<?php namespace Aero\Notify\Models;

use October\Rain\Database\Model;
use System\Behaviors\SettingsModel;

/**
 * Claves VAPID globales de la plataforma para Web Push. Se generan con
 * `php artisan notify:vapid`; los tenants no configuran nada.
 * SettingsModel no cifra: la clave privada queda en claro en system_settings.
 */
class PushSettings extends Model
{
    public $implement = [SettingsModel::class];

    public $settingsCode = 'aero_notify_push_settings';

    public $settingsFields = 'fields.yaml';

    public static function keys(): ?array
    {
        $s = static::instance();

        return $s->vapid_public && $s->vapid_private
            ? ['public' => $s->vapid_public, 'private' => $s->vapid_private, 'subject' => $s->subject ?: config('app.url')]
            : null;
    }
}
