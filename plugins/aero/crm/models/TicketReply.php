<?php namespace Aero\Crm\Models;

use Model;

/**
 * Respuesta de un ticket. Puede venir de un agente de backend (user_id) o de
 * un cliente del portal (frontend_user_id / invitado con author_name).
 */
class TicketReply extends Model
{
    public const AGENT    = 'agent';
    public const CUSTOMER = 'customer';
    public const SYSTEM   = 'system';

    public $table = 'aero_crm_ticket_replies';

    public $fillable = [
        'ticket_id', 'user_id', 'frontend_user_id', 'author_type', 'author_name',
        'body', 'is_internal',
    ];

    public $attributes = ['author_type' => self::AGENT, 'is_internal' => false];

    protected $casts = ['is_internal' => 'boolean'];

    public $belongsTo = [
        'ticket'       => [Ticket::class],
        'user'         => [\Backend\Models\User::class],
        'frontendUser' => [\RainLab\User\Models\User::class, 'key' => 'frontend_user_id'],
    ];

    protected static function boot()
    {
        parent::boot();

        // Al responder un agente, el ticket deja de estar "sin leer" para el panel.
        static::created(function (self $reply) {
            if ($reply->is_internal || $reply->author_type !== self::AGENT) {
                return;
            }

            $ticket = $reply->ticket;
            if ($ticket && $ticket->unread_for_agent) {
                $ticket->unread_for_agent = false;
                $ticket->saveQuietly();
            }
        });
    }

    /** Nombre a mostrar del autor, según su origen. */
    public function getAuthorLabelAttribute(): string
    {
        if ($this->author_type === self::SYSTEM) {
            return 'Sistema';
        }

        if ($this->author_type === self::CUSTOMER) {
            if ($this->frontendUser) {
                return $this->frontendUser->full_name ?: $this->frontendUser->email;
            }

            return $this->author_name ?: 'Cliente';
        }

        return trim(($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? '')) ?: 'Agente';
    }
}
