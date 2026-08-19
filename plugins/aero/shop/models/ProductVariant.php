<?php namespace Aero\Shop\Models;

use Model;

class ProductVariant extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_shop_product_variants';

    const SORT_ORDER = 'position';

    public $fillable = [
        'tenant_id', 'product_id', 'sku', 'price', 'compare_at_price', 'cost_price',
        'stock_quantity', 'weight_grams', 'is_active', 'position',
    ];

    public $rules = [
        'tenant_id'  => 'required|exists:aero_sites_tenants,id',
        'product_id' => 'required|exists:aero_shop_products,id',
        'sku'        => 'required|max:100',
        'price'      => 'required|numeric|min:0',
    ];

    public $belongsTo = [
        'product' => [Product::class],
    ];

    public $belongsToMany = [
        'option_values' => [
            ProductOptionValue::class,
            'table'    => 'aero_shop_variant_opt_values',
            'key'      => 'variant_id',
            'otherKey' => 'product_option_value_id',
        ],
    ];

    public $attachOne = [
        'image'        => \System\Models\File::class,
        'digital_file' => \System\Models\File::class,
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

    public function getLabelAttribute(): string
    {
        return $this->option_values->pluck('value')->implode(' / ');
    }
}
