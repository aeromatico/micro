<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\OrderItem;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use ApplicationException;
use Illuminate\Support\Facades\DB;

/**
 * Único camino para crear y mover órdenes. Todo ocurre bajo el bloqueo de la fila del evento
 * (aforo y cupos consistentes entre solicitudes simultáneas).
 *
 *  pending_payment ─(comprador avisa pago)→ verifying ─(organizador verifica)→ confirmed
 *        │ vence → expired        └→ incident ──────────────────────────────→ confirmed | cancelled
 *        └→ cancelled
 */
class OrderService
{
    /** Habilita escribir Order (el modelo rechaza cualquier otro camino). */
    public static bool $writing = false;

    /** Lo activa PaymentService: una orden con importe solo se confirma registrando un pago aprobado. */
    public static bool $paymentAuthorized = false;

    protected static function write(callable $fn)
    {
        $prev = self::$writing;
        self::$writing = true;
        try {
            return $fn();
        } finally {
            self::$writing = $prev;
        }
    }

    protected static function newCode(): string
    {
        do {
            $c = strtoupper(\Str::random(10));
        } while (Order::where('code', $c)->exists());

        return $c;
    }

    /** Una orden de pago que nadie paga ni avisa se da por abandonada pasado este tiempo (no retiene lugares: es solo orden). */
    public const ABANDON_HOURS = 72;

    /**
     * Crea la orden. NO reserva lugares: las entradas se emiten cuando la orden se confirma (compra gratuita al instante;
     * de pago, al aprobarse el cobro). Aquí solo se comprueba que haya disponibilidad en este momento.
     *
     * @param array $lines [[TicketType|int, cantidad], …]
     * @param array $buyer name, email?, phone?, document?, extra?
     * @param string $source public | manual (organizador)
     * @param array $opts idempotency_key
     */
    public static function place(Event $event, array $lines, array $buyer, string $source = 'public', array $opts = []): Order
    {
        $wanted = [];
        foreach ($lines as [$type, $qty]) {
            $id = $type instanceof TicketType ? $type->id : (int) $type;
            $qty = (int) $qty;
            if ($qty > 0) {
                $wanted[$id] = ($wanted[$id] ?? 0) + $qty;
            }
        }
        if (!$wanted) {
            throw new ApplicationException('Elige al menos una entrada.');
        }
        if (trim((string) ($buyer['name'] ?? '')) === '') {
            throw new ApplicationException('Falta el nombre del comprador.');
        }

        return DB::transaction(function () use ($event, $wanted, $buyer, $source, $opts) {
            $e = Event::lockForUpdate()->findOrFail($event->id);

            // Doble envío del mismo formulario → la misma orden, no otra.
            if (!empty($opts['idempotency_key']) && ($dup = Order::where('idempotency_key', $opts['idempotency_key'])->where('event_id', $e->id)->first())) {
                return $dup;
            }

            if (!$e->acceptsSales()) {
                throw new ApplicationException($e->platformBlocked() ?: 'Este evento ya no acepta inscripciones ni reservas.');
            }
            if ($source === 'public' && \Aero\Tickets\Models\Buyer::isBlocked($buyer['email'] ?? null)) {
                throw new ApplicationException('No fue posible procesar tu compra. Comunícate con el organizador o con soporte.');
            }
            if (array_sum($wanted) > \Aero\Tickets\Models\Settings::maxTicketsPerOrder()) {
                throw new ApplicationException('Máximo ' . \Aero\Tickets\Models\Settings::maxTicketsPerOrder() . ' entradas por orden.');
            }

            $types = TicketType::where('event_id', $e->id)->whereIn('id', array_keys($wanted))->get()->keyBy('id');
            $total = 0;
            $commission = 0.0;
            $terms = [];
            $subtotal = 0.0;
            $fees = 0.0;
            $grand = 0.0;
            $items = [];

            foreach ($wanted as $id => $qty) {
                $t = $types[$id] ?? null;
                if (!$t || !$t->is_active) {
                    throw new ApplicationException('Una de las entradas elegidas ya no está disponible.');
                }
                if ($source === 'public') {
                    if ($t->kind === 'courtesy') {
                        throw new ApplicationException('Esta entrada no está disponible al público.');
                    }
                    if (!$t->isOnSale()) {
                        throw new ApplicationException("La venta de «{$t->name}» no está abierta.");
                    }
                    if ($qty > max(1, (int) $t->max_per_order)) {
                        throw new ApplicationException("Máximo {$t->max_per_order} entradas «{$t->name}» por compra.");
                    }
                }
                $left = $t->available();
                if ($left !== null && $qty > $left) {
                    throw new ApplicationException($left > 0 ? "Solo quedan {$left} entradas «{$t->name}»." : "«{$t->name}» está agotada.");
                }

                $q = Pricing::quote($t);
                $buyerFee = $q['fee_payer'] === 'buyer' ? $q['fee'] : 0.0;
                $items[] = [$t, $qty, $q, $buyerFee];
                $commission += $q['fee'] * $qty;
                $terms[] = [
                    'ticket_type_id' => $t->id, 'name' => $t->name, 'qty' => $qty, 'base' => $q['base'], 'commission_unit' => $q['fee'],
                    'percent' => $q['terms']['percent'], 'fixed' => $q['terms']['fixed'], 'payer' => $q['terms']['payer'], 'source' => $q['terms']['source'], 'rules' => $q['terms']['rule_ids'],
                ];
                $total += $qty;
                $subtotal += $q['base'] * $qty;
                $fees += $buyerFee * $qty;
                $grand += $q['total'] * $qty;
            }

            $capLeft = $e->available();
            if ($capLeft !== null && $total > $capLeft) {
                throw new ApplicationException($capLeft > 0 ? "Solo quedan {$capLeft} lugares en el evento." : 'El evento está agotado.');
            }

            $email = isset($buyer['email']) && $buyer['email'] !== '' ? mb_strtolower(trim($buyer['email'])) : null;
            if ($source === 'public' && $email) {
                // Prevención de reservas duplicadas: una orden en curso por correo y evento.
                if ($open = Order::where('event_id', $e->id)->where('buyer_email', $email)->where('source', 'public')->whereIn('status', Order::LIVE)->first()) {
                    if (!$open->isLapsed()) {
                        throw new DuplicateOrderException($open, 'Ya tienes una orden en curso para este evento.');
                    }
                }
                // Inscripciones gratuitas: una por correo (evita vaciar el aforo).
                if ($grand == 0 && Order::where('event_id', $e->id)->where('buyer_email', $email)->where('source', 'public')->where('total', 0)->where('status', 'confirmed')->exists()) {
                    throw new ApplicationException('Ya hay una inscripción gratuita con ese correo.');
                }
            }

            $paid = $grand > 0;
            $approval = $source === 'public' && $e->registration === 'approval';
            if ($approval) {
                $orderStatus = 'verifying'; $expires = null;                       // espera la aprobación del organizador
            } elseif ($paid) {
                $orderStatus = 'pending_payment'; $expires = now()->addHours(self::ABANDON_HOURS);
            } else {
                $orderStatus = 'confirmed'; $expires = null;                       // gratis: sus entradas se emiten ya
            }

            $order = self::write(function () use ($e, $buyer, $email, $source, $opts, $orderStatus, $expires, $subtotal, $fees, $grand, $total, $items, $commission, $terms) {
                $o = new Order([
                    'event_id' => $e->id, 'buyer_name' => trim($buyer['name']),
                    'buyer_email' => $email, 'buyer_phone' => $buyer['phone'] ?? null, 'buyer_document' => $buyer['document'] ?? null,
                    'buyer_extra' => $buyer['extra'] ?? null,
                ]);
                $o->code = self::newCode();
                $o->idempotency_key = $opts['idempotency_key'] ?? null;
                $o->status = $orderStatus;
                $o->source = $source;
                $o->currency = $items[0][2]['currency'];
                $o->subtotal = round($subtotal, 2);
                $o->fees = round($fees, 2);
                $o->total = round($grand, 2);
                $o->quantity = $total;
                $o->commission_total = round($commission, 2);
                $o->commission_terms = $terms;
                $o->expires_at = $expires;
                $o->confirmed_at = $orderStatus === 'confirmed' ? now() : null;
                $o->save();

                return $o;
            });

            foreach ($items as [$t, $qty, $q, $buyerFee]) {
                OrderItem::create([
                    'tenant_id' => $e->tenant_id, 'order_id' => $order->id, 'ticket_type_id' => $t->id, 'name' => $t->name, 'kind' => $t->kind,
                    'quantity' => $qty, 'unit_price' => $q['base'], 'unit_fee' => $buyerFee,
                    'subtotal' => round($q['base'] * $qty, 2), 'fees' => round($buyerFee * $qty, 2), 'total' => round($q['total'] * $qty, 2),
                ]);
            }
            if ($orderStatus === 'confirmed') {
                self::issueTickets($order);
            }

            if ($email) {
                \Aero\Tickets\Models\Buyer::noteOrder($email, $buyer['name'] ?? null, $buyer['phone'] ?? null);
            }

            \Event::fire('aero.tickets.orderCreated', [$order]);
            if ($orderStatus === 'confirmed') {
                \Event::fire('aero.tickets.orderConfirmed', [$order]);
            }

            return $order;
        });
    }

    /** Ejecuta $fn con la orden bloqueada y refrescada. */
    protected static function locked(Order $order, callable $fn)
    {
        return DB::transaction(function () use ($order, $fn) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            $r = $fn($o);
            $order->setRawAttributes($o->getAttributes(), true);

            return $r;
        });
    }

    /** El comprador avisa que pagó (con referencia): la orden deja de poder abandonarse mientras se verifica. */
    public static function markVerifying(Order $order, ?string $reference = null): void
    {
        self::locked($order, function (Order $o) use ($reference) {
            if ($o->status === 'verifying') {
                return; // idempotente
            }
            if ($o->status !== 'pending_payment' || $o->isLapsed()) {
                throw new ApplicationException($o->isLapsed() || $o->status === 'expired' ? 'La orden venció. Haz una nueva compra.' : 'Esta orden ya no admite aviso de pago.');
            }
            self::write(function () use ($o, $reference) {
                $o->status = 'verifying';
                $o->expires_at = null;
                $o->verifying_at = now();
                $o->payment_reference = $reference ? mb_substr($reference, 0, 255) : null;
                $o->save();
            });
            \Event::fire('aero.tickets.orderVerifying', [$o]);
        });
    }

    /**
     * El organizador verificó el cobro (o aprobó la inscripción): se EMITEN las entradas. Es el único momento en que se
     * asignan lugares, así que si mientras tanto se agotó el cupo lanza excepción y el pago queda para devolución.
     * Una orden vencida (abandonada) también se confirma si su pago llega tarde y todavía hay lugares.
     */
    public static function confirm(Order $order): void
    {
        self::locked($order, function (Order $o) {
            if ($o->status === 'confirmed') {
                return;
            }
            $allowed = ['pending_payment', 'verifying', 'incident'] + (self::$paymentAuthorized ? [3 => 'expired'] : []);
            if (!in_array($o->status, $allowed, true)) {
                throw new ApplicationException($o->status === 'expired' ? 'La orden venció. Emite una nueva.' : 'Una orden ' . mb_strtolower(Order::$statuses[$o->status]) . ' no se puede confirmar.');
            }
            if ((float) $o->total > 0 && !self::$paymentAuthorized) {
                throw new ApplicationException('Una orden de pago se confirma aprobando o registrando su pago.');
            }

            self::issueTickets($o);

            self::write(function () use ($o) {
                $o->status = 'confirmed';
                $o->confirmed_at = now();
                $o->expires_at = null;
                $o->incident_note = null;
                $o->save();
            });
            \Event::fire('aero.tickets.orderConfirmed', [$o]);
        });
    }

    /**
     * Emite las entradas de la orden (una por persona) bajo el bloqueo del evento, comprobando el aforo y el cupo de cada
     * tipo en ese instante. Nunca emite dos veces. Las órdenes anteriores a «sin reservas» que aún tienen entradas
     * retenidas simplemente se activan.
     *
     * @throws ApplicationException si el evento no vende o ya no hay lugares
     */
    protected static function issueTickets(Order $o): void
    {
        $e = Event::lockForUpdate()->findOrFail($o->event_id);

        if ($held = $o->tickets()->whereIn('status', ['reserved', 'pending'])->get()) {
            if ($held->isNotEmpty()) {
                $o->tickets()->whereIn('status', ['reserved', 'pending'])->update(['status' => 'valid', 'expires_at' => null, 'paid_at' => (float) $o->total > 0 ? now() : null]);

                return;
            }
        }
        if ($o->tickets()->whereIn('status', ['valid', 'used'])->exists()) {
            return;
        }
        if (!$e->acceptsSales()) {
            throw new ApplicationException($e->platformBlocked() ?: 'El evento ya no acepta ventas.');
        }

        $items = OrderItem::where('order_id', $o->id)->orderBy('id')->get();
        $need = (int) $items->sum('quantity');
        $left = $e->available();
        if ($left !== null && $need > $left) {
            throw new ApplicationException($left > 0 ? "Solo quedan {$left} lugares en el evento." : 'El evento se agotó.');
        }
        foreach ($items as $it) {
            $t = TicketType::find($it->ticket_type_id);
            $tl = $t?->available();
            if (!$t || !$t->is_active || ($tl !== null && $it->quantity > $tl)) {
                throw new ApplicationException("Ya no hay «{$it->name}» disponibles.");
            }
        }

        $commission = collect((array) $o->commission_terms)->keyBy('ticket_type_id');
        $total = max(1, $need);
        $n = 0;
        Ticket::$viaIssuer = true;
        try {
            foreach ($items as $it) {
                $t = TicketType::find($it->ticket_type_id);
                for ($i = 0; $i < $it->quantity; $i++, $n++) {
                    $tk = new Ticket([
                        'event_id' => $e->id, 'ticket_type_id' => $it->ticket_type_id, 'session_id' => $t?->session_id,
                        'holder_name' => mb_substr(trim($o->buyer_name) . ($total > 1 && $n > 0 ? ' (' . ($n + 1) . '/' . $total . ')' : ''), 0, 255),
                        'holder_email' => $n === 0 ? $o->buyer_email : null, 'holder_phone' => $n === 0 ? $o->buyer_phone : null, 'holder_document' => $n === 0 ? $o->buyer_document : null,
                        'status' => 'valid', 'source' => $o->source === 'public' ? 'registration' : $o->source,
                        'order_id' => $o->id, 'order_code' => $o->code,
                        'base_amount' => $it->unit_price, 'fee_amount' => $commission[$it->ticket_type_id]['commission_unit'] ?? $it->unit_fee,
                        'amount' => round($it->unit_price + $it->unit_fee, 2), 'currency' => $o->currency, 'paid_at' => (float) $o->total > 0 ? now() : null,
                    ]);
                    $tk->save();
                }
            }
        } finally {
            Ticket::$viaIssuer = false;
        }
    }

    /** Pago rechazado: la orden vuelve a esperar pago (nuevo plazo para reintentar). */
    public static function resumePayment(Order $order): void
    {
        self::locked($order, function (Order $o) {
            if (!in_array($o->status, ['verifying', 'incident'], true) || (float) $o->total <= 0) {
                return;
            }
            self::write(function () use ($o) {
                $o->status = 'pending_payment';
                $o->expires_at = now()->addHours(self::ABANDON_HOURS);
                $o->save();
            });
        });
    }

    /** Algo no cuadra (monto, comprobante…): conserva el cupo y espera resolución. */
    public static function incident(Order $order, ?string $note = null): void
    {
        self::locked($order, function (Order $o) use ($note) {
            if (!in_array($o->status, ['pending_payment', 'verifying', 'incident'], true) || $o->isLapsed()) {
                throw new ApplicationException('Solo una orden pendiente o en verificación puede pasar a incidencia.');
            }
            self::write(function () use ($o, $note) {
                $o->status = 'incident';
                $o->incident_note = $note ? mb_substr($note, 0, 1000) : $o->incident_note;
                $o->expires_at = null;
                $o->save();
            });
            $o->tickets()->where('status', 'reserved')->update(['status' => 'pending', 'expires_at' => null]);
            \Event::fire('aero.tickets.orderIncident', [$o]);
        });
    }

    /** Cancela y libera el cupo. Las entradas ya usadas impiden cancelar; las cobradas quedan con reembolso pendiente. */
    public static function cancel(Order $order, ?string $reason = null): void
    {
        self::locked($order, function (Order $o) use ($reason) {
            if ($o->status === 'cancelled') {
                return;
            }
            if (in_array($o->status, ['expired'], true)) {
                throw new ApplicationException('La orden ya venció.');
            }
            if ($o->tickets()->where('status', 'used')->exists()) {
                throw new ApplicationException('Alguien ya ingresó con esta orden; no se puede cancelar.');
            }
            foreach ($o->tickets()->whereIn('status', ['reserved', 'pending', 'valid'])->get() as $t) {
                $t->refund_pending = $t->status === 'valid' && (float) $t->amount > 0;
                $t->status = 'cancelled';
                $t->save();
            }
            self::write(function () use ($o, $reason) {
                $o->status = 'cancelled';
                $o->cancelled_at = now();
                $o->cancel_reason = $reason ? mb_substr($reason, 0, 255) : null;
                $o->expires_at = null;
                $o->save();
            });
            \Event::fire('aero.tickets.orderCancelled', [$o]);
        });
    }

    /** Vence las órdenes sin pagar cuya reserva terminó. Devuelve cuántas. */
    public static function expireLapsed(): int
    {
        $n = 0;
        Order::where('status', 'pending_payment')->whereNotNull('expires_at')->where('expires_at', '<=', now())->pluck('id')->each(function ($id) use (&$n) {
            DB::transaction(function () use ($id, &$n) {
                $o = Order::lockForUpdate()->find($id);
                if (!$o || $o->status !== 'pending_payment' || !$o->expires_at || $o->expires_at->isFuture()) {
                    return;
                }
                $o->tickets()->where('status', 'reserved')->update(['status' => 'expired']);
                self::write(function () use ($o) {
                    $o->status = 'expired';
                    $o->save();
                });
                $n++;
                \Event::fire('aero.tickets.orderExpired', [$o]);
            });
        });

        return $n;
    }
}
