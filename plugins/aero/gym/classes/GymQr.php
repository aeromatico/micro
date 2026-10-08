<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\GymSettings;
use Aero\Sites\Models\Domain;
use Aero\Sites\Models\Tenant;

/**
 * QR rotativo del gimnasio (modo gym_qr). Sin estado: el token lleva el tenant
 * y la ventana de tiempo, firmados con la clave de la app. Vale la ventana
 * actual y la anterior, para que un QR recién escaneado no caduque en el aire.
 */
class GymQr
{
    public function ttl(int $tenantId): int
    {
        $s = (int) (GymSettings::forTenant($tenantId)->qr_rotation_seconds ?: 30);

        return max(10, min(600, $s));
    }

    public function token(int $tenantId): string
    {
        return $this->build($tenantId, intdiv(time(), $this->ttl($tenantId)));
    }

    /** Devuelve el tenant del token si es válido y vigente; null si no. */
    public function verify(string $token): ?int
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_digit($parts[1])) {
            return null;
        }
        $tenantId = (int) $parts[0];
        $window = intdiv(time(), $this->ttl($tenantId));

        foreach ([$window, $window - 1] as $w) {
            if ((int) $parts[1] === $w && hash_equals($this->build($tenantId, $w), $token)) {
                return $tenantId;
            }
        }

        return null;
    }

    /** Dirección que abre el móvil del socio al escanear. */
    public function url(int $tenantId): string
    {
        return $this->siteBase($tenantId) . '/gym?checkin=' . $this->token($tenantId);
    }

    protected function build(int $tenantId, int $window): string
    {
        $sig = substr(hash_hmac('sha256', "gym-qr|{$tenantId}|{$window}", (string) config('app.key')), 0, 20);

        return "{$tenantId}.{$window}.{$sig}";
    }

    /** Dominio propio si existe; si no, {handle}.{dominio raíz}. */
    protected function siteBase(int $tenantId): string
    {
        $scheme = request()->getScheme();
        $tenant = Tenant::find($tenantId);
        $domain = Domain::where('tenant_id', $tenantId)->orderByDesc('is_primary')->orderBy('is_subdomain')->value('domain');
        if ($domain) {
            return $scheme . '://' . $domain;
        }

        return $scheme . '://' . $tenant?->handle . '.' . ($tenant?->rootDomain?->domain ?? request()->getHost());
    }
}
