<?php namespace Aero\Pos\Models;

use Model;

class CashMovement extends Model
{
    public $table = 'aero_pos_cash_movements';

    public $fillable = ['tenant_id', 'shift_id', 'type', 'amount', 'reason', 'user_id'];

    public $belongsTo = [
        'shift' => [Shift::class],
        'user'  => [\Backend\Models\User::class, 'key' => 'user_id'],
    ];
}
