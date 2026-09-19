<?php namespace Aero\Chat\Models;

use Model;

class ChatOrder extends Model
{
    public $table = 'aero_chat_orders';

    public $fillable = ['tenant_id', 'conversation_id', 'user_id', 'order_id', 'notify_on_paid', 'paid_notified_at'];

    protected $dates = ['paid_notified_at'];

    protected $casts = ['notify_on_paid' => 'boolean'];
}
