<?php namespace Aero\Viral\Classes\Kyc;

/** Verificación de la firma de los webhooks de Didit (HMAC-SHA256, ventana de 5 minutos). */
class DiditWebhook
{
    public const MAX_SKEW = 300;

    /** @param array<string,string|null> $headers x-signature, x-signature-v2 (+ -previous), x-timestamp */
    public static function verify(string $rawBody, array $headers, string $secret, ?int $now = null): bool
    {
        if ($secret === '') {
            return false;
        }
        $ts = (int) ($headers['x-timestamp'] ?? 0);
        if ($ts <= 0 || abs(($now ?? time()) - $ts) > self::MAX_SKEW) {
            return false;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return false;
        }

        $raw = hash_hmac('sha256', $rawBody, $secret);
        $v2 = hash_hmac('sha256', self::canonical($payload), $secret);

        foreach (['x-signature' => $raw, 'x-signature-previous' => $raw, 'x-signature-v2' => $v2, 'x-signature-v2-previous' => $v2] as $h => $expected) {
            $given = (string) ($headers[$h] ?? '');
            if ($given !== '' && hash_equals($expected, $given)) {
                return true;
            }
        }

        return false;
    }

    /** JSON con claves ordenadas, compacto y con Unicode intacto (igual que la referencia de Didit). */
    public static function canonical(array $data): string
    {
        return json_encode(self::sortKeys($data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    protected static function sortKeys($v)
    {
        if (!is_array($v)) {
            return $v;
        }
        $isList = array_is_list($v);
        $v = array_map([self::class, 'sortKeys'], $v);
        if (!$isList) {
            ksort($v, SORT_STRING);
        }

        return $v;
    }

    /** Estado de Didit → estado de KycCheck. Los no listados se tratan como «pendiente». */
    public static function mapStatus(string $status): string
    {
        return match ($status) {
            'Approved' => 'verified',
            'Declined' => 'rejected',
            'Expired', 'Abandoned', 'Kyc Expired' => 'expired',
            default => 'pending',   // In Review, In Progress, Not Started, Awaiting User, Resubmitted
        };
    }
}
