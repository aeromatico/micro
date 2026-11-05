<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Guest;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use ApplicationException;
use Illuminate\Support\Facades\DB;

/**
 * Único camino para emitir entradas: valida estado del evento (un evento
 * cancelado, borrador o terminado no vende ni reserva), cupo del evento y del
 * tipo, ventana de venta y tope por pedido, todo bajo bloqueo de la fila del
 * evento para que dos pedidos simultáneos no sobrepasen el aforo.
 */
class TicketIssuer
{
    /**
     * Compatibilidad: emite entradas de UN tipo creando su orden (ver OrderService::place).
     *
     * @param array $holder name, email?, phone?, document?, names[]?
     * @param string $source registration (público) | guest | manual (organizador)
     * @return Ticket[]
     */
    public static function issue(Event $event, TicketType $type, array $holder, int $qty = 1, string $source = 'manual', ?Guest $guest = null, ?string $orderCode = null): array
    {
        if ($qty < 1) {
            throw new ApplicationException('La cantidad debe ser al menos 1.');
        }
        $order = OrderService::place(
            $event, [[$type, $qty]],
            ['name' => $holder['name'] ?? '', 'email' => $holder['email'] ?? null, 'phone' => $holder['phone'] ?? null, 'document' => $holder['document'] ?? null],
            $source === 'registration' ? 'public' : $source, $guest, ['names' => $holder['names'] ?? []]
        );

        return $order->tickets()->orderBy('id')->get()->all();
    }

    /**
     * Respuesta de un invitado: confirma $count personas (≤ su grupo) o declina. Cada persona recibe su propia
     * entrada (con su nombre si se dio). Pagada → una orden de reservas que se confirma al cobrar.
     * Idempotente: repetir la misma respuesta no emite entradas nuevas.
     *
     * @param string[] $names nombres de los asistentes (el primero es el invitado); los faltantes se rellenan.
     */
    public static function rsvp(Guest $guest, bool $attend, int $count = 1, array $names = []): Guest
    {
        return DB::transaction(function () use ($guest, $attend, $count, $names) {
            $g = Guest::lockForUpdate()->findOrFail($guest->id);
            $event = Event::findOrFail($g->event_id);

            if ($g->isRevoked()) {
                throw new ApplicationException('Esta invitación fue revocada.');
            }
            if (!$event->acceptsSales()) {
                throw new ApplicationException('Este evento ya no acepta confirmaciones.');
            }
            if ($g->tickets()->where('status', 'used')->exists()) {
                throw new ApplicationException('Ya ingresaste al evento; no se puede cambiar la respuesta.');
            }

            $count = max(1, min($count, (int) $g->party_size));
            $live = $g->tickets()->holding()->count();

            // Misma respuesta que ya está registrada: no hacer nada (evita duplicados por doble envío).
            if ($attend && $g->rsvp === 'confirmed' && (int) $g->confirmed_count === $count && $live === $count) {
                return $g;
            }
            if (!$attend && $g->rsvp === 'declined') {
                return $g;
            }

            // Cambiar de respuesta libera lo emitido antes.
            $g->tickets()->whereIn('status', ['pending', 'reserved', 'valid'])->update(['status' => 'cancelled']);

            if ($attend) {
                $type = $g->ticket_type
                    ?: $event->ticket_types()->where('is_active', true)->whereIn('kind', ['free', 'courtesy'])->orderBy('sort_order')->first()
                    ?: $event->ticket_types()->where('is_active', true)->orderBy('sort_order')->first();
                if (!$type) {
                    throw new ApplicationException('El evento no tiene entradas disponibles.');
                }

                $holders = [];
                for ($i = 0; $i < $count; $i++) {
                    $holders[] = mb_substr(trim((string) ($names[$i] ?? '')) ?: ($i === 0 ? $g->name : $g->name . ' (acompañante ' . $i . ')'), 0, 255);
                }
                OrderService::place($event, [[$type, $count]], [
                    'name' => $g->name, 'email' => $g->email, 'phone' => $g->phone,
                ], 'guest', $g, ['names' => $holders]);
                $g->rsvp = 'confirmed';
                $g->confirmed_count = $count;
            } else {
                $g->rsvp = 'declined';
                $g->confirmed_count = 0;
            }

            $g->responded_at = now();
            $g->save();

            \Event::fire('aero.tickets.guestResponded', [$g]);

            return $g;
        });
    }
}
