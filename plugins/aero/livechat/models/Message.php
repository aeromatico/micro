<?php namespace Aero\Livechat\Models;

use Backend\Models\User;
use Model;

class Message extends Model
{
    public const CONTACT = 'contact', AGENT = 'agent', SYSTEM = 'system';

    public $table = 'aero_livechat_messages';

    public $fillable = ['conversation_id', 'sender_type', 'sender_id', 'body'];

    public $belongsTo = [
        'conversation' => [Conversation::class],
        'agent'        => [User::class, 'key' => 'sender_id'],
    ];
}
