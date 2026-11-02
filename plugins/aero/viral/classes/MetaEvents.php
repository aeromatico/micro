<?php namespace Aero\Viral\Classes;

use Aero\Viral\Models\Application;
use Aero\Viral\Models\KycCheck;

/**
 * Conversiones de Meta (Pixel + Conversions API) del embudo de creadores.
 * El navegador dispara el Pixel con el mismo event_id que manda el servidor,
 * para que Meta deduplique. Sin credenciales de CAPI no hace nada.
 *
 *  - Lead: postulación enviada (event_id = lead_viral_{uuid}).
 *  - CompleteRegistration: identidad verificada (solo servidor; sin navegador).
 */
class MetaEvents
{
    public static function subscribe(): void
    {
        $e = \Event::class;
        $e::listen('aero.viral.applicationSubmitted', fn (Application $a) => static::lead($a));
        $e::listen('aero.viral.kycVerified', fn (KycCheck $k) => static::verified($k));
    }

    public static function leadId(Application $app): string
    {
        return 'lead_viral_' . $app->uuid;
    }

    public static function lead(Application $app): void
    {
        $client = static::client();
        if (!class_exists($client) || !$client::isConfigured()) {
            return;
        }
        $tracking = $client::captureRequestTracking('viral_' . $app->user_id);
        static::send('Lead', static::leadId($app), $tracking, $app, ['content_category' => $app->track]);
    }

    public static function verified(KycCheck $check): void
    {
        if ($app = $check->application) {
            static::send('CompleteRegistration', 'kyc_viral_' . $check->id, ['external_id' => 'viral_' . $app->user_id], $app, ['content_category' => $app->track]);
        }
    }

    protected static function send(string $event, string $id, array $tracking, Application $app, array $custom): void
    {
        $client = static::client();
        if (!class_exists($client) || !$client::isConfigured()) {
            return;
        }
        $user = $app->user;
        $data = ['email' => $user?->email, 'phone' => $app->phone, 'first_name' => $user?->first_name, 'country' => 'bo'];
        // Tras la respuesta: la llamada a Meta no demora al creador.
        dispatch(fn () => $client::send($event, $id, $tracking, $data, $custom))->afterResponse();
    }

    protected static function client(): string
    {
        return '\\Aero\\Sites\\Classes\\Meta\\ConversionsApiClient';
    }
}
