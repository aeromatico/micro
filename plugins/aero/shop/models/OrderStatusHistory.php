<?php namespace Aero\Shop\Models;

use Model;

class OrderStatusHistory extends Model
{
    public $table = 'aero_shop_order_status_history';
    public $timestamps = false;

    public $fillable = ['order_id', 'from_status', 'to_status', 'changed_by_backend_user_id', 'note', 'created_at'];

    protected $dates = ['created_at'];

    public $belongsTo = [
        'order' => [Order::class],
    ];

    public function beforeCreate()
    {
        $this->created_at = $this->created_at ?: now();
    }
}
