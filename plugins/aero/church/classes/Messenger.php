<?php namespace Aero\Church\Classes;

/**
 * Único punto de salida a WhatsApp (vía Aero.Hello, integración blanda). NUNCA lanza.
 * Silenciado en pruebas y por `Messenger::$muted`: ninguna prueba ni demo escribe a un teléfono real.
 */
class Messenger
{
    public static bool $muted = false;

    public static function available(): bool
    {
        return class_exists(\Aero\Hello\Classes\Hello::class) && !self::$muted && !app()->environment('testing');
    }

    /** @return array{0: bool, 1: ?string} [enviado, error] */
    public static function whatsapp(?int $tenantId, string $phone, string $body, ?string $name = null): array
    {
        if (!self::available()) {
            return [false, 'WhatsApp no disponible'];
        }

        try {
            \Aero\Hello\Classes\Hello::send($phone, $body, ['platform' => 'whatsapp', 'tenant_id' => $tenantId, 'name' => $name]);

            return [true, null];
        } catch (\Throwable $e) {
            \Log::warning('[church] WhatsApp no enviado: ' . $e->getMessage());

            return [false, mb_substr($e->getMessage(), 0, 200)];
        }
    }
}
