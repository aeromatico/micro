<?php namespace Aero\Sites\Models;

use Model;

class TenantUser extends Model
{
    public $table = 'aero_sites_tenant_users';

    public $timestamps = true;

    public $fillable = ['tenant_id', 'user_id', 'role'];

    public $belongsTo = [
        'tenant' => [Tenant::class],
        'user'   => [\RainLab\User\Models\User::class, 'key' => 'user_id'],
    ];

    public function getTenantIdOptions(): array
    {
        return Tenant::orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function getUserIdOptions(): array
    {
        if (!class_exists(\RainLab\User\Models\User::class)) {
            return [];
        }

        return \RainLab\User\Models\User::orderBy('email')
            ->get()
            ->mapWithKeys(fn($u) => [$u->id => "{$u->email} ({$u->name})"])
            ->toArray();
    }

    public function getRoleOptions(): array
    {
        return [
            'admin'     => 'Admin',
            'moderator' => 'Moderador',
            'user'      => 'Usuario',
        ];
    }
}
