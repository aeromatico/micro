<?php namespace Aero\Crm\Models;

use Aero\Crm\Classes\TenantUsers;
use Model;

/**
 * Departamento de la mesa de ayuda. tenant_id NULL = plataforma (sitio
 * principal); con valor = departamento de ese tenant.
 */
class Department extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_departments';

    public $fillable = ['tenant_id', 'name', 'description', 'color', 'email', 'is_active', 'sort_order'];

    public $rules = [
        'tenant_id' => 'nullable|exists:aero_sites_tenants,id',
        'name'      => 'required|max:255',
        'email'     => 'nullable|email',
    ];

    public $attributes = ['is_active' => true];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public $hasMany = [
        'tickets' => [Ticket::class],
    ];

    public $belongsToMany = [
        'users' => [
            \Backend\Models\User::class,
            'table'    => 'aero_crm_department_user',
            'key'      => 'department_id',
            'otherKey' => 'user_id',
            'pivot'    => ['is_lead'],
        ],
    ];

    /** Departamentos de un ámbito: un tenant, o la plataforma si es null. */
    public function scopeInScope($query, ?int $tenantId)
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereNull('tenant_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getUsersOptions(): array
    {
        return TenantUsers::options(
            $this->exists ? $this->tenant_id : TenantUsers::currentTenantId(),
            $this->exists ? $this->users()->pluck('backend_users.id')->all() : []
        );
    }

    public function getUsersCountAttribute(): int
    {
        return $this->users()->count();
    }
}
