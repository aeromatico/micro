<?php namespace Aero\Crm\Models;

use Model;

class TicketReply extends Model
{
    public $table = 'aero_crm_ticket_replies';

    public $fillable = ['ticket_id', 'user_id', 'body', 'is_internal'];

    public $belongsTo = [
        'ticket' => [Ticket::class],
        'user'   => [\Backend\Models\User::class],
    ];
}
