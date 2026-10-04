<?php namespace Aero\Pos\Models;

use Model;

class Payment extends Model
{
    public $table = 'aero_pos_payments';

    public $fillable = [
        'tenant_id', 'sale_id', 'shift_id', 'payment_method_id', 'amount', 'tendered', 'change_given',
        'reference', 'status', 'user_id',
    ];

    public $belongsTo = [
        'sale'   => [Sale::class],
        'shift'  => [Shift::class],
        'method' => [PaymentMethod::class, 'key' => 'payment_method_id'],
        'user'   => [\Backend\Models\User::class, 'key' => 'user_id'],
    ];
}
