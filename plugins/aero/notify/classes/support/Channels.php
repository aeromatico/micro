<?php namespace Aero\Notify\Classes\Support;

/**
 * Lista canónica de canales. Los drivers reales llegan en fases posteriores;
 * esto es lo que permite que reglas y plantillas ya se configuren contra un
 * vocabulario único en vez de strings sueltos por todo el código.
 */
class Channels
{
    public const EMAIL    = 'email';
    public const INAPP    = 'inapp';
    public const WHATSAPP = 'whatsapp';
    public const SMS      = 'sms';
    public const TELEGRAM = 'telegram';
    public const WEBHOOK  = 'webhook';

    public static function options(): array
    {
        return [
            self::EMAIL    => 'Email',
            self::INAPP    => 'En la aplicación',
            self::WHATSAPP => 'WhatsApp',
            self::SMS      => 'SMS',
            self::TELEGRAM => 'Telegram',
            self::WEBHOOK  => 'Webhook',
        ];
    }

    public static function codes(): array
    {
        return array_keys(static::options());
    }

    /** Canales cuyo mensaje lleva asunto además de cuerpo. */
    public static function withSubject(): array
    {
        return [self::EMAIL, self::INAPP];
    }

    public static function exists(string $code): bool
    {
        return in_array($code, static::codes(), true);
    }
}
