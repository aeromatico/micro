<?php namespace Aero\Tickets\Models;

use Model;

/**
 * Función de un evento (otra fecha, hora o lugar con aforo propio). La
 * estructura está lista; hoy el evento usa su propia fecha/lugar/capacidad.
 */
class Session extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Tickets\Classes\BelongsToEvent;

    public $table = 'aero_tickets_sessions';
    public $fillable = ['event_id', 'name', 'starts_at', 'ends_at', 'venue_name', 'address', 'city', 'lat', 'lng', 'capacity', 'status', 'sort_order'];
    public $rules = ['name' => 'required|string|max:255', 'ends_at' => 'nullable|date|after:starts_at', 'capacity' => 'nullable|integer|min:1'];
    public $dates = ['starts_at', 'ends_at'];
    public $belongsTo = ['event' => [Event::class, 'key' => 'event_id']];
    public $hasMany = ['ticket_types' => [TicketType::class, 'key' => 'session_id'], 'tickets' => [Ticket::class, 'key' => 'session_id']];
}
