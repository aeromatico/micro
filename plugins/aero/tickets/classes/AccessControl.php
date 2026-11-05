<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Scan;
use Aero\Tickets\Models\Scanner;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Validación de entradas en puerta. Cada lectura queda registrada (válida o no) con su operador.
 *
 * El ingreso es atómico: el paso `valid → used` es un UPDATE condicional y, además, `scans.ok_ticket_id`
 * es único: aunque dos operadores lean el mismo QR en el mismo milisegundo, solo uno obtiene «ok»;
 * el otro ve «ya utilizada» con la hora y el operador del primero. Requiere conexión (MVP, sin modo offline).
 */
class AccessControl
{
    public static function adminOperator(): array
    {
        $u = \BackendAuth::getUser();
        if (!$u) {
            throw new \ApplicationException('Se necesita un usuario autenticado.');
        }

        return ['type' => 'admin', 'id' => $u->id, 'name' => trim($u->first_name . ' ' . $u->last_name) ?: $u->login];
    }

    public static function scannerOperator(Scanner $s): array
    {
        return ['type' => 'scanner', 'id' => $s->id, 'name' => $s->name];
    }

    /** ¿Este operador puede validar este evento? Admin: eventos visibles de su tenant. Escáner: solo los asignados. */
    public static function allowed(array $operator, Event $event, ?Scanner $scanner = null): bool
    {
        if ($operator['type'] === 'scanner') {
            return $scanner && $scanner->id === $operator['id'] && $scanner->canScan($event);
        }

        return Event::visible()->whereKey($event->id)->exists();
    }

    public static function counts(Event $event): array
    {
        $in = Ticket::where('event_id', $event->id)->where('status', 'used')->count();
        $issued = Ticket::where('event_id', $event->id)->whereIn('status', ['valid', 'used'])->count();

        return ['in' => $in, 'issued' => $issued, 'remaining' => max(0, $issued - $in)];
    }

    protected static function deny(string $result, string $message, Event $event, array $operator, string $method, ?Ticket $t, ?string $code): array
    {
        $scan = Scan::create([
            'tenant_id' => $event->tenant_id, 'event_id' => $event->id, 'ticket_id' => $t?->id, 'code' => $code, 'holder_name' => $t?->holder_name,
            'result' => $result, 'method' => $method, 'operator_type' => $operator['type'], 'operator_id' => $operator['id'],
            'operator_name' => $operator['name'], 'message' => mb_substr($message, 0, 250),
        ]);

        return self::response(false, $result, $message, $event, $t, $scan);
    }

    protected static function response(bool $allowed, string $result, string $message, Event $event, ?Ticket $t, ?Scan $scan, array $extra = []): array
    {
        $type = $t && $t->ticket_type_id ? TicketType::find($t->ticket_type_id) : null;

        return [
            'allowed' => $allowed, 'result' => $result, 'title' => $allowed ? 'ACCESO PERMITIDO' : 'ACCESO DENEGADO', 'message' => $message,
            'ticket' => $t && !in_array($result, ['wrong_event'], true) ? [
                'holder' => $t->holder_name, 'type' => $type?->name, 'seq' => $t->seq, 'code' => $t->code,
            ] : null,
            'at' => $scan?->scanned_at?->format('H:i:s'), 'scan_id' => $scan?->id, 'counts' => self::counts($event),
        ] + $extra;
    }

    /**
     * @param string $scan lo leído (QR firmado o código escrito)
     * @param string $method qr | code | manual
     */
    public static function validate(string $scan, Event $event, array $operator, string $method = 'qr', ?Scanner $scanner = null): array
    {
        if (!self::allowed($operator, $event, $scanner)) {
            return self::deny('not_allowed', 'No tienes permiso para validar este evento.', $event, $operator, $method, null, null);
        }

        $code = Ticket::parseScan($scan);
        if ($code === null) {
            return self::deny('invalid', 'QR no válido: la firma no coincide.', $event, $operator, $method, null, null);
        }

        // La entrada se busca dentro del tenant del evento: un código de otro espacio simplemente no existe aquí.
        $t = Ticket::forTenant((int) $event->tenant_id)->where('code', $code)->first();
        if (!$t) {
            return self::deny('invalid', 'Entrada no encontrada.', $event, $operator, $method, null, $code);
        }
        if ((int) $t->event_id !== (int) $event->id) {
            return self::deny('wrong_event', 'Esta entrada es de otro evento.', $event, $operator, $method, $t, $code);
        }

        $event = Event::find($event->id);      // estado fresco
        if ($event->status === 'cancelled') {
            return self::deny('event_cancelled', 'El evento fue cancelado.', $event, $operator, $method, $t, $code);
        }
        if ($event->status === 'finished') {
            return self::deny('expired', 'El evento ya finalizó: la entrada está vencida.', $event, $operator, $method, $t, $code);
        }
        if ($block = $event->platformBlocked()) {
            return self::deny('event_not_open', $block . ' No se permite el ingreso.', $event, $operator, $method, $t, $code);
        }
        if (!in_array($event->status, ['published', 'ongoing'], true)) {
            return self::deny('event_not_open', 'El evento todavía no está abierto.', $event, $operator, $method, $t, $code);
        }

        // Entrada emitida de verdad: la orden de origen (si existe) debe estar confirmada.
        if ($t->order_id && Order::where('id', $t->order_id)->value('status') !== 'confirmed' && $t->status === 'valid') {
            return self::deny('not_issued', 'La orden de esta entrada no está confirmada.', $event, $operator, $method, $t, $code);
        }

        try {
            return DB::transaction(function () use ($t, $event, $operator, $method, $code) {
                $won = Ticket::whereKey($t->id)->where('status', 'valid')->update([
                    'status' => 'used', 'checked_in_at' => now(), 'checked_in_by' => mb_substr($operator['name'], 0, 120),
                ]);

                if ($won) {
                    $scan = Scan::create([
                        'tenant_id' => $event->tenant_id, 'event_id' => $event->id, 'ticket_id' => $t->id, 'ok_ticket_id' => $t->id, 'code' => $code,
                        'holder_name' => $t->holder_name, 'result' => 'ok', 'method' => $method, 'operator_type' => $operator['type'],
                        'operator_id' => $operator['id'], 'operator_name' => $operator['name'], 'message' => 'Ingreso registrado',
                    ]);
                    \Event::fire('aero.tickets.ticketCheckedIn', [$t->refresh(), $scan]);

                    return self::response(true, 'ok', 'Ingreso registrado. Adelante.', $event, $t->refresh(), $scan);
                }

                return self::explainFailure($t->refresh(), $event, $operator, $method, $code);
            });
        } catch (QueryException $e) {
            // La restricción única dio la carrera por perdida: otro operador ya registró este ingreso.
            return self::explainFailure($t->refresh(), $event, $operator, $method, $code);
        }
    }

    protected static function explainFailure(Ticket $t, Event $event, array $operator, string $method, string $code): array
    {
        if ($t->status === 'used') {
            $first = Scan::where('ok_ticket_id', $t->id)->first();
            $when = ($first?->scanned_at ?: $t->checked_in_at)?->format('d/m H:i:s');
            $msg = 'Esta entrada YA fue registrada' . ($when ? " el {$when}" : '') . (($first?->operator_name ?: $t->checked_in_by) ? ' por ' . ($first?->operator_name ?: $t->checked_in_by) : '') . '.';
            $scan = Scan::create([
                'tenant_id' => $event->tenant_id, 'event_id' => $event->id, 'ticket_id' => $t->id, 'code' => $code, 'holder_name' => $t->holder_name,
                'result' => 'already_used', 'method' => $method, 'operator_type' => $operator['type'], 'operator_id' => $operator['id'],
                'operator_name' => $operator['name'], 'message' => mb_substr($msg, 0, 250),
            ]);

            return self::response(false, 'already_used', $msg, $event, $t, $scan, ['first_at' => $when, 'first_by' => $first?->operator_name ?: $t->checked_in_by]);
        }

        [$result, $msg] = match ($t->status) {
            'cancelled' => ['cancelled', 'Entrada anulada: no permite el ingreso.'],
            'expired' => ['expired', 'La entrada está vencida.'],
            default => ['not_issued', 'La entrada aún no fue emitida (pendiente de pago o aprobación).'],
        };

        return self::deny($result, $msg, $event, $operator, $method, $t, $code);
    }

    /** Búsqueda manual dentro de UN evento (nombre, correo, teléfono o código). Máx. 15 resultados. */
    public static function search(Event $event, string $q, int $limit = 15): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 3) {
            return [];
        }
        // ESCAPE '!' funciona igual en MySQL y SQLite: % y _ del texto buscado no actúan como comodines.
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';

        return Ticket::where('event_id', $event->id)->where('tenant_id', $event->tenant_id)
            ->whereIn('status', ['valid', 'used', 'cancelled', 'pending', 'reserved'])
            ->where(function ($w) use ($like) {
                foreach (['holder_name', 'holder_email', 'holder_phone', 'code'] as $col) {
                    $w->orWhereRaw("{$col} LIKE ? ESCAPE '!'", [$like]);
                }
            })
            ->orderBy('holder_name')->limit($limit)->get()
            ->map(fn ($t) => [
                'holder' => $t->holder_name, 'code' => $t->code, 'seq' => $t->seq, 'type' => $t->ticket_type_id ? TicketType::find($t->ticket_type_id)?->name : null,
                'status' => $t->status, 'state' => $t->state_label, 'checked_in_at' => $t->checked_in_at?->format('H:i'), 'can_enter' => $t->status === 'valid',
            ])->all();
    }

    /** Últimas lecturas del evento para mostrar al operador. */
    public static function recent(Event $event, int $limit = 10): array
    {
        return Scan::where('event_id', $event->id)->orderByDesc('id')->limit($limit)->get()
            ->map(fn ($s) => ['at' => $s->scanned_at?->format('H:i:s'), 'result' => $s->result, 'label' => $s->result_label, 'holder' => $s->holder_name, 'by' => $s->operator_name])->all();
    }
}
