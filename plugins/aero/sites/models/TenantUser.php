<?php namespace Aero\Sites\Models;

use Model;

class TenantUser extends Model
{
    public $table = 'aero_sites_tenant_users';

    public $timestamps = true;

    public $fillable = ['tenant_id', 'user_id', 'role'];

    public $belongsTo = [
        'tenant' => [Tenant::class],
        'user'   => [\Backend\Models\User::class, 'key' => 'user_id'],
    ];

    public function getTenantIdOptions(): array
    {
        return Tenant::orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function getUserIdOptions(): array
    {
        return \Backend\Models\User::orderBy('email')
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => static::userLabel($u)])
            ->toArray();
    }

    // Usado en tenant_user_form.yaml
    public function getAdminCandidateOptions(): array
    {
        return \Backend\Models\User::orderBy('email')
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => static::userLabel($u)])
            ->toArray();
    }

    protected static function userLabel(\Backend\Models\User $u): string
    {
        $name = trim("{$u->first_name} {$u->last_name}") ?: $u->login ?: '';
        return $name ? "{$u->email} — {$name}" : $u->email;
    }

    public function getUserEmailAttribute(): string
    {
        return $this->user?->email ?? '—';
    }

    public function getUserNameAttribute(): string
    {
        $u = $this->user;
        if (!$u) return '—';
        return trim("{$u->first_name} {$u->last_name}") ?: $u->login ?: $u->email;
    }

    public function getRoleOptions(): array
    {
        return [
            'admin'     => 'Admin',
            'moderator' => 'Moderador',
            'user'      => 'Usuario',
        ];
    }

    // Converts duplicate inserts into updates (RelationController always does INSERT)
    public function beforeCreate(): bool
    {
        if (!$this->tenant_id || !$this->user_id) {
            return true;
        }

        $existing = static::where('tenant_id', $this->tenant_id)
            ->where('user_id', $this->user_id)
            ->first();

        if ($existing) {
            $existing->role = $this->role;
            $existing->save();
            return false; // cancel the duplicate INSERT; RelationController refreshes list anyway
        }

        return true;
    }
}
