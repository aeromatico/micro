<?php namespace Aero\Chat\Models;

use Model;

class ChatEvent extends Model
{
    public $table = 'aero_chat_events';

    public $fillable = ['tenant_id', 'conversation_id', 'user_id', 'type', 'body', 'data'];

    public $jsonable = ['data'];
}
