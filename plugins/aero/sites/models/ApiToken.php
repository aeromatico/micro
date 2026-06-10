<?php namespace Aero\Sites\Models;

use Model;
use Str;

class ApiToken extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sites_api_tokens';

    public $fillable = [
        'tenant_id', 'name', 'token', 'abilities',
        'last_used_at', 'expires_at',
    ];

    protected $dates = ['last_used_at', 'expires_at'];

    protected $casts = [
        'abilities' => 'array',
    ];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'name'      => 'required|max:100',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public static function generateToken(): array
    {
        $plain = Str::random(40);
        $hashed = hash('sha256', $plain);
        return ['plain' => $plain, 'hashed' => $hashed];
    }

    public static function findByPlainToken(string $plainToken): ?self
    {
        $hashed = hash('sha256', $plainToken);
        return static::where('token', $hashed)->first();
    }

    public function hasAbility(string $ability): bool
    {
        $abilities = $this->abilities ?? [];
        return in_array('*', $abilities) || in_array($ability, $abilities);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function touchLastUsed(): void
    {
        $this->timestamps = false;
        $this->update(['last_used_at' => now()]);
        $this->timestamps = true;
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
