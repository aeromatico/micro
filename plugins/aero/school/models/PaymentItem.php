<?php namespace Aero\School\Models;

use Model;

class PaymentItem extends Model
{
    public $table = 'aero_school_payment_items';

    public $timestamps = false;

    public $fillable = ['payment_id', 'charge_id', 'amount'];

    public $belongsTo = [
        'payment' => [Payment::class, 'key' => 'payment_id'],
        'charge'  => [Charge::class, 'key' => 'charge_id'],
    ];
}
