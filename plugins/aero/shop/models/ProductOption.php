<?php namespace Aero\Shop\Models;

use Model;

class ProductOption extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_shop_product_options';

    public $fillable = ['tenant_id', 'product_id', 'name', 'sort_order'];

    public $rules = [
        'tenant_id'  => 'required|exists:aero_sites_tenants,id',
        'product_id' => 'required|exists:aero_shop_products,id',
        'name'       => 'required|max:100',
    ];

    public $belongsTo = [
        'product' => [Product::class],
    ];

    public $hasMany = [
        'values' => [ProductOptionValue::class],
    ];

    public function beforeValidate()
    {
        if (!$this->tenant_id && $this->product_id) {
            $this->tenant_id = Product::find($this->product_id)?->tenant_id;
        }
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
