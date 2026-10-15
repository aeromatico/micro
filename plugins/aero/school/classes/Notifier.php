<?php namespace Aero\School\Classes;

/** WhatsApp vía Aero.Hello (integración blanda: sin Hello no pasa nada). */
class Notifier
{
    /** Pruebas y demos: no envía nada y deja el aviso en $log. */
    public static bool $muted = false;

    public static array $log = [];

    public static function available(): bool
    {
        return class_exists(\Aero\Hello\Classes\Hello::class);
    }

    public static function whatsapp(?int $tenantId, ?string $phone, string $body, ?string $name = null, ?string $mediaUrl = null): bool
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if (!$phone) {
            return false;
        }
        if (self::$muted) {
            self::$log[] = compact('tenantId', 'phone', 'body');

            return true;
        }
        if (!self::available()) {
            return false;
        }

        try {
            $options = ['platform' => 'whatsapp', 'tenant_id' => $tenantId, 'name' => $name];
            if ($mediaUrl) {
                $options['media_url'] = $mediaUrl;
            }
            \Aero\Hello\Classes\Hello::send($phone, $body, $options);

            return true;
        } catch (\Throwable $e) {
            \Log::warning('[school] WhatsApp no enviado: ' . $e->getMessage());

            return false;
        }
    }

    /** Reemplaza {marcadores} en una plantilla. */
    public static function render(?string $template, string $default, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{' . $k . '}'] = (string) $v;
        }

        return strtr($template ?: $default, $map);
    }

    public static function schoolName(?int $tenantId): string
    {
        $s = \Aero\School\Models\Settings::where('tenant_id', $tenantId)->first();
        if ($s && $s->school_name) {
            return $s->school_name;
        }

        try {
            if (class_exists(\Aero\Sites\Models\Tenant::class) && ($name = \Aero\Sites\Models\Tenant::whereKey($tenantId)->value('name'))) {
                return (string) $name;
            }
        } catch (\Throwable $e) {
            // sin Sites (pruebas): nombre genérico
        }

        return 'el colegio';
    }
}
