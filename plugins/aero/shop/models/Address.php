<?php namespace Aero\Shop\Models;

use Model;

class Address extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_addresses';

    public $fillable = [
        'tenant_id', 'customer_id', 'type', 'full_name', 'phone', 'address_line1',
        'address_line2', 'city', 'state_province', 'postal_code', 'country_code', 'is_default',
        'latitude', 'longitude', 'location_label',
    ];

    public $rules = [
        'tenant_id'     => 'required|exists:aero_sites_tenants,id',
        'customer_id'   => 'required|exists:aero_shop_customers,id',
        'type'          => 'required|in:shipping,billing',
        'full_name'     => 'required|max:150',
        // Con coordenadas (ubicación compartida por chat) la dirección y la ciudad son opcionales.
        'address_line1' => 'required_without_all:latitude,longitude|nullable|max:200',
        'city'          => 'required_without_all:latitude,longitude|nullable|max:100',
        'country_code'  => 'required|size:2',
    ];

    public $belongsTo = [
        'customer' => [Customer::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
