<?php namespace Aero\Shop\Models;

use Model;

class PaymentGateway extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_shop_payment_gateways';

    public $fillable = [
        'tenant_id', 'driver', 'name', 'instructions', 'config',
        'is_active', 'requires_manual_confirmation', 'sort_order',
    ];

    protected $jsonable = ['config'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'driver'    => 'required|max:50',
        'name'      => 'required|max:150',
    ];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
