<?php namespace Aero\Tickets\Tests;

use Aero\Tickets\Classes\OrderService;
use Aero\Tickets\Classes\PaymentService;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Payment;
use Aero\Tickets\Models\PaymentEvent;
use Aero\Tickets\Models\PaymentProof;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use PluginTestCase;

class PaymentsTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        \Aero\Tickets\Models\Settings::clearInternalCache();
        \Aero\Tickets\Classes\CommissionResolver::flush();
        \Aero\Tickets\Classes\OrganizerService::flush();
    }

    protected function admin(string $name = 'Ana Admin'): array
    {
        return ['type' => 'admin', 'id' => 7, 'name' => $name];
    }

    protected function paidOrder(array $ev = [], int $qty = 1, array $buyer = []): array
    {
        $e = Event::create($ev + ['tenant_id' => 1, 'name' => 'Evento ' . uniqid(), 'starts_at' => now()->addDay()]);
        $e->status = 'published';
        $e->save();
        $t = TicketType::create(['event_id' => $e->id, 'name' => 'General', 'kind' => 'paid', 'price' => 50]);
        $o = OrderService::place($e, [[$t, $qty]], $buyer + ['name' => 'Beto Comprador', 'email' => 'beto' . uniqid() . '@mail.com', 'phone' => '70011122']);

        return [$e, $t, $o];
    }

    protected function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    public function testStartCreatesPendingPaymentWithHistoryAndIsIdempotent(): void
    {
        [, , $o] = $this->paidOrder();
        $this->assertArrayHasKey('transfer', PaymentService::availableMethods($o));   // sin canales configurados, siempre hay transferencia

        $p = PaymentService::start($o, 'transfer');
        $this->assertSame(['pending', 50.0], [$p->status, (float) $p->amount]);
        $this->assertSame($p->id, PaymentService::start($o, 'transfer')->id);
        $this->assertSame(1, Payment::count());
        $this->assertSame(['created'], PaymentEvent::where('payment_id', $p->id)->pluck('action')->all());

        $this->expectException(\ApplicationException::class);
        PaymentService::start($o, 'qr');     // QR no configurado
    }

    public function testProofAndReferenceDoNotApproveAnything(): void
    {
        [$e, , $o] = $this->paidOrder();
        $p = PaymentService::start($o, 'transfer');

        try { PaymentService::submit($p, 'x'); $this->fail('referencia muy corta'); } catch (\ApplicationException $x) { $this->assertStringContainsString('referencia', $x->getMessage()); }

        $p = PaymentService::submit($p, 'TRF-0001', 'Beto C.', ['content' => $this->png(), 'name' => 'comp.png']);

        $this->assertSame('submitted', $p->status);
        $this->assertSame('verifying', $o->fresh()->status);                       // en verificación, NO se confirma
        $this->assertSame(0, $o->tickets()->count());                              // y todavía no hay entrada
        $this->assertSame([], $e->revenue());
        $this->assertSame(1, PaymentProof::where('payment_id', $p->id)->count());
        $this->assertSame(['created', 'submitted', 'proof_uploaded'], PaymentEvent::where('payment_id', $p->id)->orderBy('id')->pluck('action')->all());

        // idempotente: informar otra vez no duplica nada
        PaymentService::submit($p, 'TRF-0001');
        $this->assertSame(1, PaymentEvent::where('payment_id', $p->id)->where('action', 'submitted')->count());
    }

    public function testProofIsEncryptedAndOnlyRealImagesOrPdfAreAccepted(): void
    {
        [, , $o] = $this->paidOrder();
        $p = PaymentService::start($o, 'transfer');

        $proof = PaymentProof::storeFor($p, $this->png(), '../../mi comprobante?.png');
        $this->assertStringNotContainsString('PNG', $proof->getRawOriginal('content'));   // cifrado
        $this->assertSame($this->png(), $proof->binary());
        $this->assertSame('image/png', $proof->mime);
        $this->assertStringNotContainsString('/', $proof->name);

        foreach (['<?php echo 1;', str_repeat('a', PaymentProof::MAX_BYTES + 1)] as $bad) {
            try { PaymentProof::storeFor($p, $bad, 'x.png'); $this->fail('debía rechazar'); } catch (\ApplicationException $x) { $this->assertNotEmpty($x->getMessage()); }
        }
    }

    public function testApprovalConfirmsOrderIssuesTicketsOnceAndRecordsWhoVerified(): void
    {
        [$e, , $o] = $this->paidOrder([], 2);
        $p = PaymentService::submit(PaymentService::start($o, 'transfer'), 'TRF-77');

        try { PaymentService::approve($p, 99.00, null, $this->admin()); $this->fail('monto distinto'); } catch (\ApplicationException $x) { $this->assertStringContainsString('no coincide', $x->getMessage()); }
        $this->assertSame('submitted', $p->fresh()->status);

        $p = PaymentService::approve($p, 100.00, 'Verificado en BNB', $this->admin('Ana Admin'));
        $this->assertSame('approved', $p->status);
        $this->assertSame([7, 'Ana Admin', 'admin'], [$p->verified_by, $p->verified_by_name, $p->verified_by_actor]);
        $this->assertSame('confirmed', $o->fresh()->status);
        $this->assertSame(['valid', 'valid'], $o->tickets()->pluck('status')->all());
        $this->assertSame(100.0, $e->revenue()['BOB']);
        $this->assertSame(100.0, $e->paymentStats()['approved']['BOB']);

        // segunda aprobación (doble clic / otro admin): no cambia nada ni emite de nuevo
        PaymentService::approve($p->fresh(), 100.00, null, $this->admin('Otro'));
        $this->assertSame(2, Ticket::where('order_id', $o->id)->count());
        $this->assertSame(1, PaymentEvent::where('payment_id', $p->id)->where('action', 'approved')->count());
        $this->assertSame('Ana Admin', $p->fresh()->verified_by_name);
    }

    public function testRejectNeedsReasonAndGivesBuyerANewWindowUntilAttemptLimit(): void
    {
        [, , $o] = $this->paidOrder();
        $p = PaymentService::submit(PaymentService::start($o, 'transfer'), 'FALSA-1');

        try { PaymentService::reject($p, '', $this->admin()); $this->fail(); } catch (\ApplicationException $x) { $this->assertStringContainsString('motivo', $x->getMessage()); }

        $p = PaymentService::reject($p, 'No aparece en la cuenta', $this->admin());
        $this->assertSame(['rejected', 'No aparece en la cuenta'], [$p->status, $p->reject_reason]);
        $o = $o->fresh();
        $this->assertSame('pending_payment', $o->status);
        $this->assertNotNull($o->expires_at);
        $this->assertSame(0, $o->tickets()->count());

        for ($i = 0; $i < 4; $i++) {                   // 4 intentos más, todos rechazados
            $q = PaymentService::submit(PaymentService::start($o->fresh(), 'transfer'), 'F-' . $i);
            PaymentService::reject($q, 'No coincide', $this->admin());
        }
        $this->expectException(\ApplicationException::class);   // al 5.º rechazo ya no hay más intentos
        PaymentService::start($o->fresh(), 'transfer');
    }

    public function testLatePaymentIsAcceptedWhenThereIsRoomOtherwiseGoesToRefund(): void
    {
        [$e, $t, $o] = $this->paidOrder(['capacity' => 1]);
        $p = PaymentService::start($o, 'transfer');
        Order::where('id', $o->id)->update(['expires_at' => now()->subMinute()]);
        Order::expireLapsed();
        $this->assertSame('expired', $o->fresh()->status);
        $this->assertSame('expired', $p->fresh()->status);

        // El comprador igual pagó: lo informa tarde → queda marcado como tardío.
        $p = PaymentService::submit($p->fresh(), 'TRF-TARDE');
        $this->assertTrue((bool) $p->is_late);
        $this->assertSame('expired', $o->fresh()->status);

        // Hay cupo: se acepta y se emiten las entradas, una sola vez.
        $p = PaymentService::approve($p, 50.00, null, $this->admin());
        $this->assertSame('approved', $p->status);
        $this->assertTrue((bool) $p->is_late);
        $this->assertSame('confirmed', $o->fresh()->status);
        $this->assertSame(1, $o->tickets()->where('status', 'valid')->count());
        $this->assertGreaterThanOrEqual(1, PaymentEvent::where('payment_id', $p->id)->where('action', 'late')->count());

        // Segundo caso: antes de aprobar su pago tardío, otro compradó confirmó y se llevó el único lugar.
        [$e2, $t2, $o2] = $this->paidOrder(['capacity' => 1]);
        $p2 = PaymentService::start($o2, 'transfer');
        Order::where('id', $o2->id)->update(['expires_at' => now()->subMinute()]);
        Order::expireLapsed();
        $other = OrderService::place($e2, [[$t2, 1]], ['name' => 'Otra', 'email' => 'otra@mail.com', 'phone' => '7000000'], 'public');
        PaymentService::registerManual($other, 50.0, 'Efectivo, recibo 5', null, $this->admin());          // toma el único lugar

        $p2 = PaymentService::approve(PaymentService::submit($p2->fresh(), 'TRF-2'), 50.00, null, $this->admin());
        $this->assertSame('refund_pending', $p2->status);
        $this->assertTrue((bool) $p2->is_suspicious);
        $this->assertSame('expired', $o2->fresh()->status);
        $this->assertSame(0, $o2->tickets()->count());                                         // no se emitió nada

        $p2 = PaymentService::markRefunded($p2, 'DEV-55', 'Devuelto por transferencia', $this->admin());
        $this->assertSame(['refunded', 'DEV-55'], [$p2->status, $p2->refund_reference]);
    }

    public function testCancellingAPaidOrderMarksPaymentForRefund(): void
    {
        [, , $o] = $this->paidOrder();
        $p = PaymentService::approve(PaymentService::submit(PaymentService::start($o, 'transfer'), 'TRF-9'), 50.00, null, $this->admin());
        \Event::listen('aero.tickets.orderCancelled', fn ($x) => PaymentService::onOrderCancelled($x));   // el enlace vive en Plugin::boot

        $o->fresh()->cancel('El comprador pidió devolución');

        $p = $p->fresh();
        $this->assertSame('refund_pending', $p->status);
        $this->assertTrue((bool) $p->is_suspicious);
        $this->assertSame([true], $o->tickets()->pluck('refund_pending')->map(fn ($v) => (bool) $v)->all());
    }

    public function testRepeatedReferenceOrProofIsFlaggedAndNeedsANoteToApprove(): void
    {
        [, , $o1] = $this->paidOrder();
        [, , $o2] = $this->paidOrder();
        $p1 = PaymentService::submit(PaymentService::start($o1, 'transfer'), 'REF-DUP', null, ['content' => $this->png(), 'name' => 'a.png']);
        $this->assertFalse((bool) $p1->is_suspicious);

        $p2 = PaymentService::submit(PaymentService::start($o2, 'transfer'), 'ref-dup', null, ['content' => $this->png(), 'name' => 'b.png']);
        $this->assertTrue((bool) $p2->is_suspicious);
        $this->assertStringContainsString('referencia', $p2->flag_reason);
        $this->assertStringContainsString('comprobante', $p2->flag_reason);

        try { PaymentService::approve($p2, 50.00, null, $this->admin()); $this->fail('exige nota'); } catch (\ApplicationException $x) { $this->assertStringContainsString('sospechoso', $x->getMessage()); }
        $this->assertSame('approved', PaymentService::approve($p2->fresh(), 50.00, 'Es el mismo cliente, dos órdenes y un solo depósito de 100', $this->admin())->status);
    }

    public function testGatewayConfirmationIsIdempotentAndMismatchGoesToIncident(): void
    {
        [, , $o] = $this->paidOrder();
        $p = PaymentService::start($o, 'transfer');
        Payment::where('id', $p->id)->update(['provider' => 'pay_qr', 'provider_ref' => 'QR-123']);

        $this->assertNull(PaymentService::confirmFromProvider('pay_qr', 'NO-EXISTE', 50.0));

        // monto distinto: no se confirma, se marca y la orden pasa a incidencia
        $bad = PaymentService::confirmFromProvider('pay_qr', 'QR-123', 20.0);
        $this->assertTrue((bool) $bad->is_suspicious);
        $this->assertNotSame('approved', $bad->status);
        $this->assertSame('incident', $o->fresh()->status);

        $ok = PaymentService::confirmFromProvider('pay_qr', 'QR-123', 50.0, ['bank' => 'bnb']);
        $this->assertSame(['approved', 'gateway'], [$ok->status, $ok->verified_by_actor]);
        $this->assertSame('confirmed', $o->fresh()->status);

        PaymentService::confirmFromProvider('pay_qr', 'QR-123', 50.0);   // reintento del webhook
        $this->assertSame(1, $o->tickets()->where('status', 'valid')->count());
        $this->assertSame(1, PaymentEvent::where('payment_id', $p->id)->where('action', 'auto_confirmed')->count());
    }

    public function testManualPaymentNeedsNoteAndPaidOrdersCannotBeConfirmedBypassingPayments(): void
    {
        [, , $o] = $this->paidOrder();

        try { $o->confirm(); $this->fail('no se confirma sin pago'); } catch (\ApplicationException $x) { $this->assertStringContainsString('pago', $x->getMessage()); }
        try { PaymentService::registerManual($o, 50.0, 'ok', null, $this->admin()); $this->fail('nota mínima'); } catch (\ApplicationException $x) { $this->assertStringContainsString('Explica', $x->getMessage()); }

        $p = PaymentService::registerManual($o, 50.0, 'Efectivo en puerta, recibo 33', 'REC-33', $this->admin());
        $this->assertSame(['manual', 'approved'], [$p->method, $p->status]);
        $this->assertSame('confirmed', $o->fresh()->status);
        $this->assertSame(['manual_registered', 'approved'], PaymentEvent::where('payment_id', $p->id)->orderBy('id')->pluck('action')->all());
    }

    public function testFinancialHistoryIsAppendOnlyAndPaymentsAreNeverDeleted(): void
    {
        [, , $o] = $this->paidOrder();
        $p = PaymentService::start($o, 'transfer');
        $ev = PaymentEvent::where('payment_id', $p->id)->first();

        foreach ([fn () => $ev->update(['note' => 'editado']), fn () => $ev->delete(), fn () => $p->fresh()->delete(),
                  fn () => Payment::where('id', $p->id)->first()->update(['amount' => 1])] as $try) {
            try { $try(); $this->fail('debía rechazar la modificación'); } catch (\ApplicationException $x) { $this->assertNotEmpty($x->getMessage()); }
        }
        $this->assertSame(50.0, (float) $p->fresh()->amount);
    }

    public function testApprovedPaymentEmailsTicketsOnceAndResendIsExplicit(): void
    {
        \Aero\Tickets\Classes\TicketDelivery::$log = [];
        [, , $o] = $this->paidOrder([], 2, ['email' => 'comprador@mail.com']);
        $p = PaymentService::registerManual($o, 100.0, 'Pago en oficina', null, $this->admin());

        $this->assertSame([['comprador@mail.com', 2, true]], array_map(fn ($l) => [$l['to'], $l['tickets'], $l['pdf']], \Aero\Tickets\Classes\TicketDelivery::$log));
        $this->assertNotNull($o->fresh()->tickets_sent_at);

        PaymentService::approve($p->fresh(), 100.0, null, $this->admin());          // se repite la confirmación
        $this->assertCount(1, \Aero\Tickets\Classes\TicketDelivery::$log);          // no se vuelve a enviar

        $this->assertTrue(\Aero\Tickets\Classes\TicketDelivery::send($o->fresh(), true));   // reenvío explícito
        $this->assertCount(2, \Aero\Tickets\Classes\TicketDelivery::$log);
        $this->assertSame(2, (int) $o->fresh()->tickets_send_count);
    }

    public function testPayBridgeEventConfirmsThePaymentWithoutManualVerification(): void
    {
        [, , $o] = $this->paidOrder();
        $p = PaymentService::start($o, 'transfer');
        Payment::where('id', $p->id)->update(['provider' => 'pay_qr', 'provider_ref' => 'QR-BRIDGE']);

        // El banco confirma vía aero/pay: el mismo evento que escucha el plugin.
        \Event::fire('aero.pay.paymentReceived', [(object) ['id' => 1, 'amount' => 50], (object) ['internal_reference' => 'QR-BRIDGE', 'amount' => 50, 'bank_code' => 'bnb']]);

        $this->assertSame('approved', $p->fresh()->status);
        $this->assertSame('gateway', $p->fresh()->verified_by_actor);
        $this->assertSame('confirmed', $o->fresh()->status);
    }

    public function testStatsCountPendingRefundsLateAndSuspicious(): void
    {
        [$e, , $o] = $this->paidOrder();
        PaymentService::submit(PaymentService::start($o, 'transfer'), 'TRF-S1');
        $s = $e->paymentStats();
        $this->assertSame([1, 0, []], [$s['to_verify'], $s['refund_pending'], $s['approved']]);

        PaymentService::confirmOrder($o->fresh(), 'Verificado', $this->admin());
        $this->assertSame(50.0, $e->paymentStats()['approved']['BOB']);
        $this->assertSame(0, $e->paymentStats()['to_verify']);
    }
}
