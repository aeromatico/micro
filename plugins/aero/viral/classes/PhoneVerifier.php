<?php namespace Aero\Viral\Classes;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use October\Rain\Exception\ApplicationException;

/**
 * Verificación opcional del celular con un código de 6 dígitos por WhatsApp (Aero.Hello, cuenta de plataforma).
 * El código nunca se guarda en claro; vence a los 10 minutos y permite 5 intentos.
 * Para no usar el canal contra terceros: espera de 60 s entre envíos, 3 por número cada 30 min y 10 por IP por hora.
 */
class PhoneVerifier
{
    public const TTL = 600;
    public const MAX_ATTEMPTS = 5;
    public const COOLDOWN = 60;

    /** Reemplazable en pruebas: function (string $to, string $body): void, lanza si no se pudo enviar. */
    public static $sender = null;

    public static function available(): bool
    {
        if (static::$sender) {
            return true;
        }
        if (!class_exists(\Aero\Hello\Classes\Hello::class)) {
            return false;
        }
        try {
            \Aero\Hello\Classes\Hello::resolveAccount(['platform' => 'whatsapp']);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return array{cooldown:int} */
    public static function send(?string $rawPhone, ?string $ip): array
    {
        $phone = Catalog::normalizePhone($rawPhone);
        if (!$phone) {
            throw new ApplicationException('Escribe un celular boliviano de 8 dígitos (empieza en 6 o 7).');
        }
        if (!static::available()) {
            throw new ApplicationException('La verificación por WhatsApp no está disponible ahora.');
        }
        $token = UploadService::sessionToken();
        $cd = 'viral:otp-cd:' . $token . ':' . $phone;
        if (Cache::has($cd)) {
            throw new ApplicationException('Espera un minuto antes de pedir otro código.');
        }
        $byPhone = 'viral-otp-phone:' . $phone;
        $byIp = 'viral-otp-ip:' . $ip;
        if (RateLimiter::tooManyAttempts($byPhone, 3) || RateLimiter::tooManyAttempts($byIp, 10)) {
            throw new ApplicationException('Demasiados intentos. Prueba de nuevo más tarde.');
        }
        RateLimiter::hit($byPhone, 1800);
        RateLimiter::hit($byIp, 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put('viral:otp:' . $token . ':' . $phone, ['hash' => static::hash($code), 'tries' => 0], self::TTL);
        Cache::put($cd, 1, self::COOLDOWN);

        try {
            static::deliver(ltrim($phone, '+'), "Tu código de viral. es {$code}");
        } catch (\Throwable $e) {
            Cache::forget($cd);
            Cache::forget('viral:otp:' . $token . ':' . $phone);
            \Log::warning('aero.viral: no se pudo enviar el código por WhatsApp: ' . $e->getMessage());
            throw new ApplicationException('No pudimos enviar el código por WhatsApp. Revisa el número e intenta de nuevo.');
        }

        return ['cooldown' => self::COOLDOWN];
    }

    public static function check(?string $rawPhone, ?string $code): bool
    {
        $phone = Catalog::normalizePhone($rawPhone);
        $code = preg_replace('/\D+/', '', (string) $code);
        $key = 'viral:otp:' . UploadService::sessionToken() . ':' . $phone;
        $data = $phone ? Cache::get($key) : null;
        if (!$data) {
            throw new ApplicationException('El código venció. Pide uno nuevo.');
        }
        if ($data['tries'] >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
            throw new ApplicationException('Demasiados intentos. Pide un código nuevo.');
        }
        if (strlen($code) !== 6 || !hash_equals($data['hash'], static::hash($code))) {
            $data['tries']++;
            Cache::put($key, $data, self::TTL);
            throw new ApplicationException('Ese código no es correcto.');
        }
        Cache::forget($key);
        Cache::put('viral:otp-ok:' . UploadService::sessionToken(), $phone, now()->addHours(2));

        return true;
    }

    /** Celular verificado en esta sesión (o null). */
    public static function verifiedPhone(): ?string
    {
        return Cache::get('viral:otp-ok:' . UploadService::sessionToken());
    }

    protected static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    protected static function deliver(string $to, string $body): void
    {
        if (static::$sender) {
            (static::$sender)($to, $body);

            return;
        }
        $msg = \Aero\Hello\Classes\Hello::send($to, $body, [
            'platform' => 'whatsapp', 'queue' => false,
            // El saludo de la plantilla no debe usar el nombre de un contacto ya guardado con ese número: quien se registra es otra persona.
            'provider_payload' => ['contact_name' => ''],
        ]);
        \Aero\Hello\Jobs\SendMessageJob::dispatchSync($msg->id);
        $msg->refresh();
        if ($msg->status === 'failed') {
            throw new \RuntimeException((string) $msg->failed_reason);
        }
    }
}
