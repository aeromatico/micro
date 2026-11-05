<?php namespace Aero\Tickets\Classes;

use Aero\Tickets\Classes\Providers\Registry;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Payment;
use Aero\Tickets\Models\PaymentEvent;
use Aero\Tickets\Models\PaymentProof;
use Aero\Tickets\Models\PaymentSetting;
use ApplicationException;
use Illuminate\Support\Facades\DB;

/**
 * Único camino para crear y resolver pagos de órdenes.
 *
 * Reglas de seguridad:
 *  - Lo que sube el comprador (referencia, comprobante) NO aprueba: solo un administrador autorizado o la
 *    confirmación de un proveedor (confirmFromProvider) pasan un pago a «aprobado».
 *  - Aprobar es idempotente y atómico (bloqueo de fila): dos aprobaciones simultáneas emiten las entradas una vez.
 *  - El monto verificado debe igualar al esperado; si no, no se aprueba (rechazar o reportar inconsistencia).
 *  - Cada paso queda en un historial de solo-agregar con el actor responsable.
 *
 *  pending ─(comprador informa)→ submitted ─(admin/proveedor)→ approved ─(orden cancelada)→ refund_pending → refunded
 *     │                              └→ rejected (la orden vuelve a esperar pago)
 *     └→ cancelled | expired (la orden venció; si el dinero llega igual → pago tardío)
 */
class PaymentService
{
    public static bool $writing = false;

    protected const MAX_REJECTED_ATTEMPTS = 5;

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

    /* ---------- actores e historial ---------- */

    public static function adminActor(): array
    {
        $u = \BackendAuth::getUser();
        if (!$u) {
            throw new ApplicationException('Se necesita un administrador autenticado.');
        }

        return ['type' => 'admin', 'id' => $u->id, 'name' => trim($u->first_name . ' ' . $u->last_name) ?: $u->login];
    }

    public static function buyerActor(Order $o): array
    {
        return ['type' => 'buyer', 'id' => null, 'name' => $o->buyer_name];
    }

    protected static function log(?Payment $p, Order $o, string $action, array $actor, ?string $from = null, ?string $to = null, ?float $amount = null, ?string $note = null, array $meta = []): void
    {
        PaymentEvent::create([
            'tenant_id' => $o->tenant_id, 'payment_id' => $p?->id, 'order_id' => $o->id, 'action' => $action,
            'actor_type' => $actor['type'], 'actor_id' => $actor['id'] ?? null, 'actor_name' => $actor['name'] ?? null,
            'from_status' => $from, 'to_status' => $to, 'amount' => $amount,
            'note' => $note !== null ? mb_substr($note, 0, 500) : null, 'meta' => $meta ?: null,
        ]);
    }

    protected static function setStatus(Payment $p, string $to, array $attrs = []): string
    {
        $from = $p->status;
        self::write(function () use ($p, $to, $attrs) {
            $p->fill([]);
            foreach ($attrs as $k => $v) {
                $p->{$k} = $v;
            }
            $p->status = $to;
            $p->save();
        });

        return $from;
    }

    /* ---------- métodos disponibles ---------- */

    /** @return array<string,string> método => etiqueta que el comprador puede usar para esta orden */
    public static function availableMethods(Order $order): array
    {
        $out = [];
        foreach (Registry::all() as $prov) {
            if ($prov->isAvailable($order)) {
                $out[$prov->method()] = $prov->label();
            }
        }
        $s = PaymentSetting::forTenant($order->tenant_id);
        // Sin ningún canal configurado, la transferencia/contacto con el organizador sigue disponible.
        if ($s->transfer_enabled || !$out) {
            $out['transfer'] = 'Transferencia bancaria';
        }

        return $out;
    }

    /* ---------- comprador ---------- */

    /** Crea (o reutiliza) el intento de pago de la orden con el método elegido. */
    public static function start(Order $order, string $method): Payment
    {
        if (!array_key_exists($method, self::availableMethods($order))) {
            throw new ApplicationException('Ese método de pago no está disponible.');
        }

        $payment = DB::transaction(function () use ($order, $method) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            if ($o->status !== 'pending_payment' || $o->isLapsed() || (float) $o->total <= 0) {
                throw new ApplicationException($o->isLapsed() || $o->status === 'expired' ? 'La reserva venció. Haz una nueva compra.' : 'Esta orden no admite un nuevo pago.');
            }
            if (Payment::where('order_id', $o->id)->where('status', 'rejected')->count() >= self::MAX_REJECTED_ATTEMPTS) {
                throw new ApplicationException('Se alcanzó el máximo de intentos. Contacta al organizador.');
            }

            $live = Payment::where('order_id', $o->id)->whereIn('status', Payment::OPEN)->lockForUpdate()->get();
            foreach ($live as $p) {
                if ($p->method === $method && $p->status === 'pending') {
                    return $p;           // idempotente: mismo método, mismo intento
                }
                if ($p->status === 'submitted') {
                    throw new ApplicationException('Ya informaste un pago; espera la verificación del organizador.');
                }
            }
            foreach ($live as $p) {          // cambió de método: el intento anterior sin informar se cancela
                $from = self::setStatus($p, 'cancelled');
                self::log($p, $o, 'cancelled', self::buyerActor($o), $from, 'cancelled', null, 'Cambió de método de pago');
            }

            $p = self::write(function () use ($o, $method) {
                $p = new Payment([
                    'tenant_id' => $o->tenant_id, 'event_id' => $o->event_id, 'order_id' => $o->id, 'method' => $method,
                    'status' => 'pending', 'currency' => $o->currency, 'amount' => $o->total,
                ]);
                $p->save();

                return $p;
            });
            self::log($p, $o, 'created', self::buyerActor($o), null, 'pending', (float) $o->total, 'Método: ' . (Payment::$methods[$method] ?? $method));

            return $p;
        });

        // Proveedor en línea (QR…): fuera de la transacción, es una llamada externa.
        if ($payment->wasRecentlyCreated && ($prov = Registry::forMethod($method))) {
            try {
                $info = $prov->start($order, $payment);
                self::write(function () use ($payment, $prov, $info) {
                    $payment->provider = $prov->key();
                    $payment->provider_ref = $info['provider_ref'] ?? null;
                    $payment->save();
                });
            } catch (\Throwable $e) {
                $from = self::setStatus($payment, 'cancelled');
                self::log($payment, $order, 'cancelled', ['type' => 'system'], $from, 'cancelled', null, 'El proveedor no pudo crear el cobro');
                throw $e instanceof ApplicationException ? $e : new ApplicationException('No pudimos iniciar el pago. Intenta de nuevo.');
            }
        }

        return $payment->refresh();
    }

    /**
     * El comprador informa que pagó (referencia y, si corresponde, comprobante). NO aprueba el pago: lo deja
     * «pendiente de verificación» y retiene el cupo mientras un administrador (o el proveedor) lo confirma.
     *
     * @param array|null $proof ['content' => binario, 'name' => nombre]
     */
    public static function submit(Payment $payment, ?string $reference, ?string $sender = null, ?array $proof = null): Payment
    {
        $reference = $reference !== null ? trim($reference) : null;

        $result = DB::transaction(function () use ($payment, $reference, $sender, $proof) {
            $o = Order::lockForUpdate()->findOrFail($payment->order_id);
            $p = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($p->status === 'submitted') {
                return [$p, $o, false];                                  // idempotente
            }
            if (!in_array($p->status, ['pending', 'expired'], true)) {
                throw new ApplicationException('Este pago ya no admite avisos.');
            }
            if (in_array($o->status, ['cancelled', 'confirmed'], true)) {
                throw new ApplicationException($o->status === 'confirmed' ? 'La orden ya está confirmada.' : 'La orden fue cancelada. Si ya pagaste, contacta al organizador.');
            }

            $s = PaymentSetting::forTenant($o->tenant_id);
            $dynamicQr = $p->method === 'qr' && $p->provider && Registry::byKey($p->provider)?->present($p)['automatic'];
            if (!$dynamicQr && (!$reference || mb_strlen($reference) < 3)) {
                throw new ApplicationException('Indica la referencia o número de la operación.');
            }
            if ($p->method === 'transfer' && $s->proof_policy === 'required' && !$proof) {
                throw new ApplicationException('Adjunta el comprobante de la transferencia.');
            }

            $late = $o->status === 'expired' || $o->isLapsed();
            $flags = [];
            if ($reference) {
                $same = Payment::where('tenant_id', $p->tenant_id)->where('id', '!=', $p->id)->whereRaw('LOWER(reference) = ?', [mb_strtolower($reference)])
                    ->whereIn('status', ['submitted', 'approved', 'refund_pending', 'refunded'])->first();
                if ($same) {
                    $flags[] = 'La referencia ya se usó en el pago #' . $same->id;
                }
            }
            $hash = $proof ? hash('sha256', $proof['content']) : null;
            if ($hash && PaymentProof::where('tenant_id', $p->tenant_id)->where('sha256', $hash)->where('payment_id', '!=', $p->id)->exists()) {
                $flags[] = 'El comprobante ya se subió en otro pago';
            }
            if (Payment::where('order_id', $o->id)->where('status', 'rejected')->count() >= 3) {
                $flags[] = 'Varios intentos rechazados en esta orden';
            }

            $from = self::setStatus($p, 'submitted', [
                'reference' => $reference ?: $p->reference, 'sender_name' => $sender ? mb_substr($sender, 0, 255) : $p->sender_name,
                'submitted_at' => now(), 'is_late' => $late ?: $p->is_late,
            ]);
            self::log($p, $o, 'submitted', self::buyerActor($o), $from, 'submitted', (float) $p->amount, $reference ? 'Referencia: ' . $reference : null);

            if ($proof) {
                PaymentProof::storeFor($p, $proof['content'], $proof['name'] ?? null);
                self::log($p, $o, 'proof_uploaded', self::buyerActor($o), null, null, null, 'Comprobante adjunto (no prueba el cobro hasta ser verificado)');
            }
            if ($late) {
                self::log($p, $o, 'late', ['type' => 'system'], null, null, null, 'El comprador informó el pago después de vencer la reserva');
            }
            if ($flags) {
                self::flag($p, $o, implode('; ', $flags), ['type' => 'system']);
            }

            // La orden retiene el cupo mientras se verifica (si aún está vigente).
            if ($o->status === 'pending_payment' && !$o->isLapsed()) {
                OrderService::markVerifying($o, $reference);
            }

            return [$p->refresh(), $o, true];
        });

        [$p, $o, $changed] = $result;
        if ($changed) {
            \Event::fire('aero.tickets.paymentSubmitted', [$p, $o]);
        }

        return $p;
    }

    /* ---------- verificación (administrador / proveedor) ---------- */

    protected static function flag(Payment $p, Order $o, string $reason, array $actor): void
    {
        self::write(function () use ($p, $reason) {
            $p->is_suspicious = true;
            $p->flag_reason = mb_substr(trim(($p->flag_reason ? $p->flag_reason . '; ' : '') . $reason), 0, 250);
            $p->save();
        });
        self::log($p, $o, 'flagged', $actor, null, null, null, $reason);
        \Event::fire('aero.tickets.paymentFlagged', [$p, $reason]);
    }

    /** Aprobación por un administrador autorizado. $received = lo que realmente verificó en su banco. */
    public static function approve(Payment $payment, float $received, ?string $note = null, ?array $actor = null): Payment
    {
        return self::approveCore($payment, $received, $actor ?: self::adminActor(), $note);
    }

    /**
     * Confirmación automática de un proveedor de pago (QR con API, pasarela de tarjetas…). Idempotente por
     * (provider, provider_ref). Devuelve null si no corresponde a ningún pago de entradas.
     */
    public static function confirmFromProvider(string $provider, string $providerRef, float $amount, array $meta = []): ?Payment
    {
        $p = Payment::where('provider', $provider)->where('provider_ref', $providerRef)->first();
        if (!$p) {
            return null;
        }

        return self::approveCore($p, $amount, ['type' => 'gateway', 'id' => null, 'name' => $provider], null, $meta);
    }

    protected static function approveCore(Payment $payment, float $received, array $actor, ?string $note, array $meta = []): Payment
    {
        $after = [];

        $p = DB::transaction(function () use ($payment, $received, $actor, $note, $meta, &$after) {
            $o = Order::lockForUpdate()->findOrFail($payment->order_id);
            $p = Payment::lockForUpdate()->findOrFail($payment->id);

            if (in_array($p->status, ['approved', 'refund_pending', 'refunded'], true)) {
                return $p;                                              // ya aprobado: no se repite nada
            }
            $gateway = $actor['type'] === 'gateway';
            $allowed = $gateway ? ['pending', 'submitted', 'expired', 'cancelled', 'rejected'] : ['pending', 'submitted', 'expired'];
            if (!in_array($p->status, $allowed, true)) {
                throw new ApplicationException('Un pago ' . mb_strtolower(Payment::$statuses[$p->status]) . ' no se puede aprobar.');
            }

            // El dinero recibido debe igualar lo esperado.
            if (abs($received - (float) $p->amount) > 0.01) {
                if ($gateway) {
                    self::flag($p, $o, sprintf('El proveedor informó %s y se esperaba %s', number_format($received, 2), number_format((float) $p->amount, 2)), $actor);
                    if (in_array($o->status, ['pending_payment', 'verifying'], true) && !$o->isLapsed()) {
                        OrderService::incident($o, 'Monto recibido distinto al esperado (pago #' . $p->id . ')');
                    }

                    return $p->refresh();
                }
                throw new ApplicationException(sprintf('El monto verificado (%s) no coincide con el esperado (%s). Recházalo o reporta la inconsistencia.', number_format($received, 2), number_format((float) $p->amount, 2)));
            }
            if ($p->is_suspicious && !$gateway && (!$note || mb_strlen(trim($note)) < 5)) {
                throw new ApplicationException('Este pago está marcado como sospechoso (' . $p->flag_reason . '). Escribe una nota explicando por qué lo apruebas.');
            }

            $from = $p->status;
            $late = $p->is_late || $o->status === 'expired' || $o->isLapsed();
            $outcome = 'approved';
            $reason = null;

            if ($o->status === 'cancelled') {
                $outcome = 'refund_pending';
                $reason = 'El pago llegó con la orden cancelada';
            } elseif ($o->status === 'confirmed') {
                $outcome = 'refund_pending';
                $reason = 'La orden ya estaba confirmada con otro pago (duplicado)';
            } elseif ($late && !\Aero\Tickets\Models\Settings::reopenLatePayments()) {
                $outcome = 'refund_pending';
                $reason = 'Pago tardío: la plataforma no acepta pagos de órdenes vencidas';
            } else {
                // Aquí se asignan los lugares: si mientras tanto se agotaron, el dinero ya está recibido y queda para devolución.
                try {
                    OrderService::$paymentAuthorized = true;
                    OrderService::confirm($o);
                } catch (ApplicationException $e) {
                    $outcome = 'refund_pending';
                    $reason = ($late ? 'Pago tardío y ' : 'Se agotó el cupo antes de confirmar: ') . lcfirst($e->getMessage());
                } finally {
                    OrderService::$paymentAuthorized = false;
                }
            }

            $attrs = [
                'amount_received' => $received, 'verified_at' => now(), 'verified_by' => $actor['id'] ?? null,
                'verified_by_name' => $actor['name'] ?? null, 'verified_by_actor' => $gateway ? 'gateway' : 'admin', 'is_late' => $late,
            ];
            self::setStatus($p, $outcome, $attrs);
            self::log($p, $o, $gateway ? 'auto_confirmed' : 'approved', $actor, $from, $outcome, $received, $note, $meta);
            if ($late) {
                self::log($p, $o, 'late', $actor, null, null, $received, $outcome === 'approved' ? 'Pago tardío: se aceptó y las entradas se emitieron' : $reason);
            }
            if ($outcome === 'refund_pending') {
                self::flag($p, $o, $reason, $actor);
                self::log($p, $o, 'refund_pending', $actor, $from, 'refund_pending', $received, $reason);
                $after[] = ['aero.tickets.paymentRefundPending', [$p->refresh(), $o->refresh()]];
            } else {
                // Otros intentos abiertos de la misma orden ya no hacen falta.
                foreach (Payment::where('order_id', $o->id)->where('id', '!=', $p->id)->whereIn('status', Payment::OPEN)->get() as $other) {
                    $f = self::setStatus($other, 'cancelled');
                    self::log($other, $o, 'cancelled', ['type' => 'system'], $f, 'cancelled', null, 'La orden se pagó con el pago #' . $p->id);
                }
                $after[] = ['aero.tickets.paymentApproved', [$p->refresh(), $o->refresh()]];
            }

            return $p->refresh();
        });

        foreach ($after as [$event, $args]) {
            \Event::fire($event, $args);
        }

        return $p;
    }

    /** Rechazo con motivo. Si la orden esperaba esta verificación, vuelve a esperar pago (con ventana nueva). */
    public static function reject(Payment $payment, string $reason, ?array $actor = null): Payment
    {
        $actor ??= self::adminActor();
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) {
            throw new ApplicationException('Indica el motivo del rechazo.');
        }

        $p = DB::transaction(function () use ($payment, $reason, $actor) {
            $o = Order::lockForUpdate()->findOrFail($payment->order_id);
            $p = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($p->status === 'rejected') {
                return $p;
            }
            if (!in_array($p->status, Payment::OPEN, true)) {
                throw new ApplicationException('Un pago ' . mb_strtolower(Payment::$statuses[$p->status]) . ' no se puede rechazar.');
            }
            $from = self::setStatus($p, 'rejected', [
                'reject_reason' => mb_substr($reason, 0, 250), 'verified_at' => now(), 'verified_by' => $actor['id'] ?? null,
                'verified_by_name' => $actor['name'] ?? null, 'verified_by_actor' => $actor['type'] === 'gateway' ? 'gateway' : 'admin',
            ]);
            self::log($p, $o, 'rejected', $actor, $from, 'rejected', null, $reason);

            if (in_array($o->status, ['verifying', 'incident'], true)) {
                OrderService::resumePayment($o);
            }

            return $p->refresh();
        });

        \Event::fire('aero.tickets.paymentRejected', [$p, Order::find($p->order_id)]);

        return $p;
    }

    /** El monto, el titular o el comprobante no cuadran: se marca, la orden pasa a incidencia y el cupo sigue retenido. */
    public static function reportInconsistency(Payment $payment, string $reason, ?array $actor = null): Payment
    {
        $actor ??= self::adminActor();
        $reason = trim($reason);
        if (mb_strlen($reason) < 5) {
            throw new ApplicationException('Describe la inconsistencia.');
        }

        return DB::transaction(function () use ($payment, $reason, $actor) {
            $o = Order::lockForUpdate()->findOrFail($payment->order_id);
            $p = Payment::lockForUpdate()->findOrFail($payment->id);
            if (!in_array($p->status, Payment::OPEN, true)) {
                throw new ApplicationException('Solo un pago pendiente o por verificar puede reportarse.');
            }
            self::flag($p, $o, $reason, $actor);
            if (in_array($o->status, ['pending_payment', 'verifying'], true) && !$o->isLapsed()) {
                OrderService::incident($o, $reason);
            }

            return $p->refresh();
        });
    }

    /** Pago recibido por fuera del sitio, autorizado por un administrador (efectivo, depósito…). Exige nota. */
    public static function registerManual(Order $order, float $amount, string $note, ?string $reference = null, ?array $actor = null): Payment
    {
        $actor ??= self::adminActor();
        $note = trim($note);
        if (mb_strlen($note) < 5) {
            throw new ApplicationException('Explica cómo se recibió el pago (por ejemplo: «efectivo en puerta, recibo 123»).');
        }

        $p = DB::transaction(function () use ($order, $amount, $note, $reference, $actor) {
            $o = Order::lockForUpdate()->findOrFail($order->id);
            if (!in_array($o->status, ['pending_payment', 'verifying', 'incident', 'expired'], true)) {
                throw new ApplicationException('Esta orden no admite registrar un pago.');
            }
            if ((float) $o->total <= 0) {
                throw new ApplicationException('Esta orden no tiene importe por cobrar.');
            }
            $p = self::write(function () use ($o, $reference, $actor) {
                $p = new Payment([
                    'tenant_id' => $o->tenant_id, 'event_id' => $o->event_id, 'order_id' => $o->id, 'method' => 'manual',
                    'status' => 'submitted', 'currency' => $o->currency, 'amount' => $o->total,
                    'reference' => $reference ? mb_substr($reference, 0, 255) : null, 'submitted_at' => now(),
                ]);
                $p->save();

                return $p;
            });
            self::log($p, $o, 'manual_registered', $actor, null, 'submitted', $amount, $note);

            return $p;
        });

        return self::approveCore($p, $amount, $actor, $note);
    }

    /**
     * Atajo de «confirmar cobro» del panel: aprueba el pago que el comprador informó (si lo hay) o registra
     * uno manual con la nota del administrador. Siempre deja el pago y el historial.
     */
    public static function confirmOrder(Order $order, ?string $note = null, ?array $actor = null): ?Payment
    {
        $actor ??= self::adminActor();
        if ((float) $order->total <= 0) {                       // inscripción gratuita en espera de aprobación: no hay pago que registrar
            OrderService::confirm($order);

            return null;
        }
        $open = Payment::where('order_id', $order->id)->where('status', 'submitted')->orderByDesc('id')->first();
        if ($open) {
            return self::approveCore($open, (float) $open->amount, $actor, $note);
        }

        return self::registerManual($order, (float) $order->total, $note ?: '', null, $actor);
    }

    public static function markRefunded(Payment $payment, string $reference, ?string $note = null, ?array $actor = null): Payment
    {
        $actor ??= self::adminActor();
        if (mb_strlen(trim($reference)) < 3) {
            throw new ApplicationException('Indica la referencia de la devolución.');
        }

        $p = DB::transaction(function () use ($payment, $reference, $note, $actor) {
            $o = Order::findOrFail($payment->order_id);
            $p = Payment::lockForUpdate()->findOrFail($payment->id);
            if ($p->status === 'refunded') {
                return $p;
            }
            if ($p->status !== 'refund_pending') {
                throw new ApplicationException('Solo un pago con reembolso pendiente puede marcarse como reembolsado.');
            }
            $from = self::setStatus($p, 'refunded', ['refund_reference' => mb_substr($reference, 0, 255), 'refunded_at' => now()]);
            self::log($p, $o, 'refunded', $actor, $from, 'refunded', (float) $p->amount_received, $note ?: 'Devolución: ' . $reference);

            return $p->refresh();
        });

        \Event::fire('aero.tickets.paymentRefunded', [$p]);

        return $p;
    }

    /* ---------- reacciones al ciclo de la orden ---------- */

    /** Orden cancelada: lo cobrado queda para reembolso; los intentos sin informar se cancelan. */
    public static function onOrderCancelled(Order $o): void
    {
        foreach (Payment::where('order_id', $o->id)->whereIn('status', ['approved', 'pending', 'submitted'])->get() as $p) {
            DB::transaction(function () use ($p, $o) {
                $p = Payment::lockForUpdate()->find($p->id);
                if ($p->status === 'approved') {
                    $from = self::setStatus($p, 'refund_pending');
                    self::flag($p, $o, 'Orden cancelada con pago recibido', ['type' => 'system']);
                    self::log($p, $o, 'refund_pending', ['type' => 'system'], $from, 'refund_pending', (float) $p->amount_received, 'La orden fue cancelada: hay que devolver el pago');
                    \Event::fire('aero.tickets.paymentRefundPending', [$p->refresh(), $o]);
                } elseif (in_array($p->status, ['pending', 'submitted'], true)) {
                    $from = self::setStatus($p, 'cancelled');
                    self::log($p, $o, 'cancelled', ['type' => 'system'], $from, 'cancelled', null, 'Orden cancelada');
                }
            });
        }
    }

    /** Orden vencida: los intentos sin informar vencen también (si el dinero llega igual, será pago tardío). */
    public static function onOrderExpired(Order $o): void
    {
        foreach (Payment::where('order_id', $o->id)->where('status', 'pending')->get() as $p) {
            DB::transaction(function () use ($p, $o) {
                $p = Payment::lockForUpdate()->find($p->id);
                if ($p->status === 'pending') {
                    $from = self::setStatus($p, 'expired', ['is_late' => false]);
                    self::log($p, $o, 'expired', ['type' => 'system'], $from, 'expired', null, 'La reserva de la orden venció');
                }
            });
        }
    }
}
