<?php namespace Aero\Shop\Models;

use Model;

class TenantCurrency extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_tenant_currencies';

    public $fillable = ['tenant_id', 'currency_id', 'exchange_rate', 'is_default', 'updated_manually_at'];

    protected $dates = ['updated_manually_at'];

    public $rules = [
        'tenant_id'     => 'required|exists:aero_sites_tenants,id',
        'currency_id'   => 'required|exists:aero_shop_currencies,id',
        'exchange_rate' => 'required|numeric|min:0',
    ];

    public $belongsTo = [
        'tenant'   => [\Aero\Sites\Models\Tenant::class],
        'currency' => [Currency::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
