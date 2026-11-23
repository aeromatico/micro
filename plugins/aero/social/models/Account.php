<?php namespace Aero\Social\Models;

use Aero\Social\Classes\Networks;
use Model;

/**
 * Una cuenta de red social conectada por un tenant (página de Facebook,
 * cuenta de Instagram…). El token es del tenant y se guarda cifrado.
 */
class Account extends Model
{
    use \October\Rain\Database\Traits\Encryptable;

    public $table = 'aero_social_accounts';

    protected $encryptable = ['access_token'];

    protected $hidden = ['access_token'];

    protected $fillable = [
        'tenant_id', 'network', 'external_id', 'name', 'username', 'avatar_url',
        'access_token', 'token_expires_at', 'parent_id', 'status', 'last_error', 'connected_by', 'checked_at',
    ];

    protected $dates = ['token_expires_at', 'checked_at'];

    public $belongsTo = [
        'parent' => [Account::class, 'key' => 'parent_id'],
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereRaw('1 = 0');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function networkLabel(): string
    {
        return Networks::label($this->network);
    }

    /** «Página X (Facebook)» para listas y selectores. */
    public function displayName(): string
    {
        $handle = $this->username ? ' @' . $this->username : '';

        return ($this->name ?: $this->external_id) . $handle . ' · ' . $this->networkLabel();
    }

    public function markExpired(string $error): void
    {
        $this->status = 'expired';
        $this->last_error = mb_substr($error, 0, 1000);
        $this->save();
    }
}
