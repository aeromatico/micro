<?php namespace Aero\Chat\Models;

use Model;

class ChatCharge extends Model
{
    public $table = 'aero_chat_charges';

    public $fillable = [
        'tenant_id', 'conversation_id', 'user_id', 'qr_code_id', 'amount', 'currency',
        'description', 'status', 'notify_on_paid', 'due_at', 'paid_at',
    ];

    protected $dates = ['due_at', 'paid_at'];

    protected $casts = ['notify_on_paid' => 'boolean'];
}
