<?php namespace Aero\Shop\Models;

use Model;
use Str;

class Order extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shop_orders';

    public const STATUSES = ['pending', 'awaiting_payment', 'paid', 'fulfilled', 'cancelled', 'refunded'];

    public $fillable = [
        'tenant_id', 'customer_id', 'order_number', 'access_token', 'status', 'currency_id',
        'exchange_rate_snapshot', 'subtotal', 'discount_total', 'shipping_total',
        'tax_total', 'grand_total', 'payment_gateway_id', 'payment_reference',
        'paid_at', 'paid_confirmed_by_backend_user_id', 'fulfilled_at', 'cancelled_at',
        'cancel_reason', 'shipping_address_id', 'billing_address_id', 'notes',
        'customer_notes', 'requires_shipping',
    ];

    protected $dates = ['paid_at', 'fulfilled_at', 'cancelled_at'];

    public $rules = [
        'tenant_id'    => 'required|exists:aero_sites_tenants,id',
        'customer_id'  => 'required|exists:aero_shop_customers,id',
        'order_number' => 'required|max:50',
        'status'       => 'required|in:pending,awaiting_payment,paid,fulfilled,cancelled,refunded',
        'currency_id'  => 'required|exists:aero_shop_currencies,id',
    ];

    public $belongsTo = [
        'tenant'           => [\Aero\Sites\Models\Tenant::class],
        'customer'         => [Customer::class],
        'currency'         => [Currency::class],
        'payment_gateway'  => [PaymentGateway::class],
        'shipping_address' => [Address::class, 'key' => 'shipping_address_id'],
        'billing_address'  => [Address::class, 'key' => 'billing_address_id'],
    ];

    public $hasMany = [
        'items'          => [OrderItem::class],
        'status_history' => [OrderStatusHistory::class],
        'stock_movements' => [StockMovement::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function beforeCreate()
    {
        if (!$this->access_token) {
            $this->access_token = Str::random(40);
        }
    }
}
