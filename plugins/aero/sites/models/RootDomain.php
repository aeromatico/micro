<?php namespace Aero\Sites\Models;

use Model;

class RootDomain extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_sites_root_domains';

    public $fillable = ['domain', 'label', 'is_active', 'sort_order'];

    public $rules = [
        'domain' => 'required|unique:aero_sites_root_domains,domain',
        'label'  => 'required',
    ];

    public $hasMany = [
        'tenants' => [Tenant::class, 'key' => 'root_domain_id'],
    ];

    public function getFullDomainAttribute(): string
    {
        return $this->domain;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
