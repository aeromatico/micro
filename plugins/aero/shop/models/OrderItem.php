<?php namespace Aero\Shop\Models;

use Model;

class OrderItem extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_order_items';

    public $fillable = [
        'tenant_id', 'order_id', 'product_id', 'product_variant_id', 'product_name_snapshot',
        'variant_label_snapshot', 'sku_snapshot', 'unit_price', 'quantity', 'line_total',
        'product_type_snapshot',
    ];

    public $rules = [
        'tenant_id'              => 'required|exists:aero_sites_tenants,id',
        'order_id'                => 'required|exists:aero_shop_orders,id',
        'product_name_snapshot'   => 'required|max:200',
        'unit_price'               => 'required|numeric|min:0',
        'quantity'                 => 'required|integer|min:1',
        'line_total'                => 'required|numeric|min:0',
    ];

    public $belongsTo = [
        'order'   => [Order::class],
        'product' => [Product::class],
        'variant' => [ProductVariant::class, 'key' => 'product_variant_id'],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
