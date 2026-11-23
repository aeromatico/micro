<?php namespace Aero\Social\Models;

use Model;

/** Cuenta publicitaria (Meta act_…) conectada por un tenant. Token cifrado. */
class AdAccount extends Model
{
    use \October\Rain\Database\Traits\Encryptable;

    public $table = 'aero_social_ad_accounts';

    protected $encryptable = ['access_token'];

    protected $hidden = ['access_token'];

    protected $fillable = [
        'tenant_id', 'platform', 'external_id', 'name', 'currency', 'timezone', 'account_status',
        'access_token', 'token_expires_at', 'status', 'last_error', 'connected_by', 'synced_at',
    ];

    protected $dates = ['token_expires_at', 'synced_at'];

    public $hasMany = [
        'campaigns' => [Campaign::class, 'key' => 'ad_account_id'],
    ];

    /** Monedas sin decimales en Meta: el presupuesto va en unidades, no en centavos. */
    public const ZERO_DECIMAL = ['CLP', 'COP', 'CRC', 'HUF', 'ISK', 'IDR', 'JPY', 'KRW', 'PYG', 'TWD', 'VND'];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereRaw('1 = 0');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Factor entre la unidad mínima (presupuestos) y la moneda (gasto). */
    public function offset(): int
    {
        return in_array(strtoupper((string) $this->currency), self::ZERO_DECIMAL, true) ? 1 : 100;
    }

    public function toMinor(float $amount): int
    {
        return (int) round($amount * $this->offset());
    }

    public function toMajor(?int $minor): ?float
    {
        return $minor === null ? null : $minor / $this->offset();
    }

    public function expiresSoon(): bool
    {
        return $this->token_expires_at && $this->token_expires_at->lt(now()->addDays(7));
    }

    public function markExpired(string $error): void
    {
        $this->status = 'expired';
        $this->last_error = mb_substr($error, 0, 1000);
        $this->save();
    }

    public function displayName(): string
    {
        return ($this->name ?: $this->external_id) . ($this->currency ? " ({$this->currency})" : '');
    }
}
