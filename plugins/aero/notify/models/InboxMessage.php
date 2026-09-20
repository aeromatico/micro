<?php namespace Aero\Notify\Models;

use Model;

/** Entrada de la bandeja in-app de un usuario (canal 'inapp'). */
class InboxMessage extends Model
{
    public $table = 'aero_notify_inbox';

    protected $guarded = [];

    protected $dates = ['read_at'];

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markRead(): void
    {
        if (!$this->read_at) {
            $this->read_at = now();
            $this->save();
        }
    }
}
