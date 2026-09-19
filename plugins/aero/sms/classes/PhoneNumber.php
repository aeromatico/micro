<?php namespace Aero\Sms\Classes;

use Aero\Sms\Models\Settings;

class PhoneNumber
{
    /**
     * Devuelve E.164 (+59171234567) o null si no parece un número válido.
     * Sin '+' ni '00' se asume el prefijo país por defecto de la Configuración.
     */
    public static function normalize(?string $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $international = str_starts_with($raw, '+') || str_starts_with($raw, '00');
        $digits = preg_replace('/\D+/', '', $raw);

        if ($international) {
            $digits = preg_replace('/^00/', '', $digits);
        }
        else {
            $digits = ltrim($digits, '0');
            $digits = Settings::defaultCountryCode() . $digits;
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) ? '+' . $digits : null;
    }
}
