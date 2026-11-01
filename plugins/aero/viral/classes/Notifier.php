<?php namespace Aero\Viral\Classes;

use Aero\Viral\Models\Application;
use Aero\Viral\Models\KycCheck;

/**
 * Avisos transaccionales de Viral por correo y WhatsApp, vía el gateway Aero.Notify (eventos viral.*).
 * Nunca rompe el flujo que lo dispara: si Notify no está o falla, se registra y se sigue.
 * WhatsApp sale desde la cuenta de plataforma con la plantilla por defecto de Hello. No usar category=utility: exige Direct Send,
 * que esta cuenta no tiene habilitado (Meta rechaza con «Direct Send is not enabled»).
 */
class Notifier
{
    /** Reemplazable en pruebas: function (string $code, array $context, array $options): void. */
    public static $fire = null;

    public static function subscribe(): void
    {
        $e = \Event::class;
        $e::listen('aero.viral.applicationSubmitted', fn (Application $a) => static::submitted($a));
        $e::listen('aero.viral.kycVerified', fn (KycCheck $k) => static::kyc($k, 'viral.kyc.verified'));
        $e::listen('aero.viral.kycRejected', fn (KycCheck $k) => static::kyc($k, 'viral.kyc.rejected'));
        $e::listen('aero.viral.applicationApproved', fn (Application $a) => static::toCreator($a, 'viral.application.approved'));
        $e::listen('aero.viral.applicationChangesRequested', fn (Application $a) => static::toCreator($a, 'viral.application.changes_requested'));
        $e::listen('aero.viral.applicationRejected', fn (Application $a) => static::toCreator($a, 'viral.application.rejected'));
        $e::listen('aero.viral.levelChanged', fn (Application $a, int $from, int $to) => static::toCreator($a, 'viral.level.changed'));
    }

    public static function submitted(Application $app): void
    {
        static::toCreator($app, 'viral.application.received');
        static::send('viral.application.new', static::context($app, ['url' => url(\Backend::url('aero/viral/applications/update/' . $app->id))]), []);
    }

    public static function kyc(KycCheck $check, string $code): void
    {
        if ($app = $check->application) {
            static::toCreator($app, $code, ['reason' => (string) $check->reject_reason]);
        }
    }

    public static function toCreator(Application $app, string $code, array $extra = []): void
    {
        $user = $app->user;
        if (!$user) {
            return;
        }
        $actor = ['name' => $user->first_name, 'email' => $user->email, 'phone' => $app->phone];
        static::send($code, static::context($app, $extra), ['actor' => $actor]);
    }

    protected static function context(Application $app, array $extra = []): array
    {
        return $extra + [
            'name' => (string) ($app->user->first_name ?? 'creador'),
            'track_label' => (string) $app->track_label,
            'rate_label' => $app->rate_model ? (string) $app->rate_label : '',
            'notes' => $app->status === 'changes_requested' ? (string) $app->review_notes : '',  // las notas del rechazo son internas
            'level' => Application::LEVELS[(int) $app->level] ?? '',
            'url' => 'https://viral.market.com.bo/aplicar',
        ];
    }

    /** Tras confirmar la transacción abierta (si la hay): el job de entrega no debe correr antes del commit. */
    protected static function send(string $code, array $context, array $options): void
    {
        \DB::afterCommit(fn () => static::dispatch($code, $context, $options));
    }

    protected static function dispatch(string $code, array $context, array $options): void
    {
        try {
            if (static::$fire) {
                (static::$fire)($code, $context, $options);

                return;
            }
            if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
                return;
            }
            \Aero\Notify\Classes\Notify::fire($code, $context, $options);
        } catch (\Throwable $e) {
            \Log::warning("aero.viral: no se pudo enviar el aviso {$code}: " . $e->getMessage());
        }
    }
}
