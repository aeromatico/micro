<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
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
     * Registro manual del organizador: crea la orden de UN tipo de entrada (OrderService::place). Si es gratis o cortesía
     * las entradas se emiten en el acto; si es de pago, quedan por cobrar y se emiten al confirmar el pago.
     *
     * @param array $holder name, email?, phone?, document?
     * @return Ticket[] las entradas emitidas (vacío si la orden quedó pendiente de pago)
     */
    public static function issue(Event $event, TicketType $type, array $holder, int $qty = 1): array
    {
        if ($qty < 1) {
            throw new ApplicationException('La cantidad debe ser al menos 1.');
        }
        $order = OrderService::place(
            $event, [[$type, $qty]],
            ['name' => $holder['name'] ?? '', 'email' => $holder['email'] ?? null, 'phone' => $holder['phone'] ?? null, 'document' => $holder['document'] ?? null],
            'manual'
        );

        return $order->tickets()->orderBy('id')->get()->all();
    }
}
