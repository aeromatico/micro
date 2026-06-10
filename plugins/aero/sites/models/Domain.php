<?php namespace Aero\Sites\Models;

use Model;

class Domain extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sites_domains';

    public $fillable = ['tenant_id', 'domain', 'is_primary', 'is_subdomain'];

    public $rules = [
        'tenant_id'    => 'required|exists:aero_sites_tenants,id',
        'domain'       => 'required|unique:aero_sites_domains,domain',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];
}
