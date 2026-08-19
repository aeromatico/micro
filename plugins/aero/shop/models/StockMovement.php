<?php namespace Aero\Shop\Models;

use Model;

class StockMovement extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_stock_movements';
    public $timestamps = false;

    public $fillable = [
        'tenant_id', 'product_id', 'product_variant_id', 'type', 'quantity_delta',
        'quantity_after', 'order_id', 'note', 'created_by_backend_user_id', 'created_at',
    ];

    protected $dates = ['created_at'];

    public $rules = [
        'tenant_id'  => 'required|exists:aero_sites_tenants,id',
        'product_id' => 'required|exists:aero_shop_products,id',
        'type'       => 'required|in:sale,manual_adjustment,restock,return,initial',
    ];

    public $belongsTo = [
        'product' => [Product::class],
        'variant' => [ProductVariant::class, 'key' => 'product_variant_id'],
        'order'   => [Order::class],
    ];

    public function beforeCreate()
    {
        $this->created_at = $this->created_at ?: now();
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
