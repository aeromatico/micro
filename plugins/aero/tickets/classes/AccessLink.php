<?php namespace Aero\Tickets\Classes;

/**
 * Acceso sin cuenta a «Mis entradas»: enlace firmado y con vencimiento que se envía al correo de la compra.
 * Quien no controla ese correo no puede ver las entradas.
 */
class AccessLink
{
    protected static function sign(string $email, int $tenantId, int $exp): string
    {
        $key = hash_hmac('sha256', 'aero.tickets.mytickets.v1', (string) config('app.key'));

        return hash_hmac('sha256', $tenantId . '|' . mb_strtolower($email) . '|' . $exp, $key);
    }

    /** Parámetros para la URL de consulta. */
    public static function params(string $email, int $tenantId, int $ttlMinutes = 60): array
    {
        $exp = time() + $ttlMinutes * 60;

        return ['e' => rtrim(strtr(base64_encode(mb_strtolower($email)), '+/', '-_'), '='), 'x' => $exp, 's' => self::sign($email, $tenantId, $exp)];
    }

    /** Devuelve el correo si la firma es válida y no venció; null si no. */
    public static function verify(array $q, int $tenantId): ?string
    {
        $email = base64_decode(strtr((string) ($q['e'] ?? ''), '-_', '+/'), true);
        $exp = (int) ($q['x'] ?? 0);
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || $exp < time() || empty($q['s'])) {
            return null;
        }

        return hash_equals(self::sign($email, $tenantId, $exp), (string) $q['s']) ? $email : null;
    }
}
