<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;

/** Cifras de cupos de un evento y de cada tipo: emitidas, reservadas, pendientes, disponibles y agotado. */
class Inventory
{
    public static function counts($ticketsQuery): array
    {
        $rows = (clone $ticketsQuery)->selectRaw('status, expires_at, COUNT(*) c')->groupBy('status', 'expires_at')->get();
        $o = ['issued' => 0, 'used' => 0, 'reserved' => 0, 'pending' => 0];
        foreach ($rows as $r) {
            if ($r->status === 'valid') $o['issued'] += $r->c;
            elseif ($r->status === 'used') { $o['issued'] += $r->c; $o['used'] += $r->c; }
            elseif ($r->status === 'pending') $o['pending'] += $r->c;
            elseif ($r->status === 'reserved' && $r->expires_at && \Carbon\Carbon::parse($r->expires_at)->isFuture()) $o['reserved'] += $r->c;
        }
        $o['committed'] = $o['issued'] + $o['reserved'] + $o['pending'];

        return $o;
    }

    public static function event(Event $e): array
    {
        $c = self::counts($e->tickets());
        $c['capacity'] = $e->capacity;
        $c['available'] = $e->capacity === null ? null : max(0, $e->capacity - $c['committed']);
        $c['sold_out'] = $c['available'] === 0;

        return $c;
    }

    public static function type(TicketType $t): array
    {
        $c = self::counts(Ticket::where('ticket_type_id', $t->id));
        $own = $t->quantity === null ? null : max(0, $t->quantity - $c['committed']);
        // por id: en las listas la columna relacional `event` pisa el atributo con un string
        $event = Event::find($t->event_id);
        $ev = $event ? self::event($event)['available'] : null;
        $c['quantity'] = $t->quantity;
        // Disponible real: el menor entre el cupo propio y lo que queda del evento.
        $c['available'] = $own === null ? $ev : ($ev === null ? $own : min($own, $ev));
        $c['sold_out'] = $c['available'] === 0;

        return $c;
    }
}
