<?php namespace Aero\Tickets\Tests;

use Aero\Tickets\Classes\CommissionLedger;
use Aero\Tickets\Classes\CommissionResolver;
use Aero\Tickets\Classes\ModerationService;
use Aero\Tickets\Classes\OrderService;
use Aero\Tickets\Classes\OrganizerService;
use Aero\Tickets\Classes\PaymentService;
use Aero\Tickets\Classes\Templates;
use Aero\Tickets\Models\Audit;
use Aero\Tickets\Models\Buyer;
use Aero\Tickets\Models\CommissionEntry;
use Aero\Tickets\Models\CommissionRule;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Location;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Organizer;
use Aero\Tickets\Models\Payment;
use Aero\Tickets\Models\Scanner;
use Aero\Tickets\Models\Settings;
use Aero\Tickets\Models\Template;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use PluginTestCase;

class AdminTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Settings::clearInternalCache();
        CommissionResolver::flush();
        OrganizerService::flush();
    }

    public function tearDown(): void
    {
        // Los parámetros se guardan en memoria entre pruebas: no deben filtrarse a las demás clases.
        Settings::clearInternalCache();
        CommissionResolver::flush();
        OrganizerService::flush();
        \BackendAuth::logout();
        parent::tearDown();
    }

    /** Inicia sesión de administrador (sin tablas de Sites: solo la sesión). */
    protected function admin(): \Backend\Models\User
    {
        $u = new \Backend\Models\User(['first_name' => 'Ana', 'last_name' => 'Admin', 'login' => 'ana' . uniqid(), 'email' => uniqid() . '@x.com', 'password' => 'secret123', 'password_confirmation' => 'secret123']);
        $u->is_activated = true;
        $u->is_superuser = true;
        $u->save();
        \BackendAuth::login($u);

        return $u;
    }

    protected function setting(array $v): void
    {
        $s = Settings::instance();
        foreach ($v as $k => $x) { $s->{$k} = $x; }
        $s->save();
        Settings::clearInternalCache();
    }

    protected function event(array $o = []): Event
    {
        return Event::create($o + ['tenant_id' => 1, 'name' => 'Evento ' . uniqid(), 'description' => 'd', 'starts_at' => now()->addDay(), 'venue_name' => 'Sala', 'city' => 'La Paz', 'organizer_name' => 'Org', 'contact_phone' => '7000000']);
    }

    protected function publishable(array $o = []): Event
    {
        $e = $this->event($o);
        $cat = \Aero\Tickets\Models\Category::firstOrCreate(['slug' => 'x'], ['name' => 'X']);
        $e->category_id = $cat->id;
        $e->save();
        TicketType::create(['event_id' => $e->id, 'name' => 'General', 'kind' => 'free']);
        // portada: se simula con el attach mínimo
        \DB::table('system_files')->insert(['disk_name' => 'c' . $e->id . '.jpg', 'file_name' => 'c.jpg', 'file_size' => 1, 'content_type' => 'image/jpeg', 'field' => 'cover', 'attachment_id' => (string) $e->id, 'attachment_type' => Event::class, 'is_public' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $e->fresh();
    }

    /* ---------- organizadores ---------- */

    public function testOrganizerApprovalFlowWithHistoryAndAudit(): void
    {
        $this->setting(['require_organizer_approval' => true]);
        $o = OrganizerService::ensure(5);
        $this->assertSame('pending', $o->status);
        $this->assertStringContainsString('pendiente', OrganizerService::publishBlock(5));

        $this->admin();
        OrganizerService::reject($o, 'Falta el documento de identidad');
        $this->assertSame('rejected', $o->fresh()->status);
        $this->assertStringContainsString('rechazada', OrganizerService::publishBlock(5));

        // vuelve a revisión cuando corrige sus datos
        OrganizerService::submit($o->fresh(), ['legal_name' => 'Eventos SRL', 'document' => '123456']);
        $this->assertSame('pending', $o->fresh()->status);

        OrganizerService::approve($o->fresh(), 'Documentos verificados');
        $this->assertNull(OrganizerService::publishBlock(5));
        OrganizerService::suspend($o->fresh(), 'Reclamos de compradores');
        $this->assertStringContainsString('suspendida', OrganizerService::publishBlock(5));
        OrganizerService::reinstate($o->fresh(), null);

        $this->assertSame(['organizer.created', 'organizer.rejected', 'organizer.updated', 'organizer.approved', 'organizer.suspended', 'organizer.approved'], Audit::where('subject_type', 'organizer')->orderBy('id')->pluck('action')->all());
        $this->assertSame('approved', $o->fresh()->status);
    }

    public function testOrganizerStatusCannotBeChangedOutsideTheService(): void
    {
        $o = OrganizerService::ensure(6);
        $o->status = 'suspended';
        $this->expectException(\ApplicationException::class);
        $o->save();
    }

    public function testRejectAndSuspendNeedAReason(): void
    {
        $this->admin();
        $o = OrganizerService::ensure(7);
        $this->expectException(\ApplicationException::class);
        OrganizerService::suspend($o, 'x');
    }

    public function testApprovalNotRequiredByDefaultAndSuspendedOrganizerBlocksSalesAndCatalog(): void
    {
        $this->admin();
        $this->assertSame('approved', OrganizerService::ensure(1)->status);          // sin exigir aprobación → aprobado
        $e = $this->event(['tenant_id' => 1, 'visibility' => 'public']);
        $e->status = 'published'; $e->save();
        $t = TicketType::create(['event_id' => $e->id, 'name' => 'G', 'kind' => 'free']);
        $this->assertCount(1, Event::forTenant(1)->listedPublicly()->get());

        OrganizerService::suspend(OrganizerService::ensure(1), 'Fraude reportado');
        OrganizerService::flush();
        $this->assertCount(0, Event::forTenant(1)->listedPublicly()->get());           // oculto
        $this->assertFalse($e->fresh()->acceptsSales());
        $this->expectException(\ApplicationException::class);
        OrderService::place($e->fresh(), [[$t, 1]], ['name' => 'A', 'email' => 'a@x.com', 'phone' => '7000000']);
    }

    /* ---------- moderación de eventos ---------- */

    public function testEventReviewHidesUntilApprovedAndRejectionReturnsToDraft(): void
    {
        $this->admin();
        $this->setting(['require_event_review' => true]);
        $e = $this->publishable(['visibility' => 'public']);
        $e->publish();
        $e = $e->fresh();

        $this->assertSame(['published', 'pending'], [$e->status, $e->moderation]);
        $this->assertFalse($e->acceptsSales());
        $this->assertCount(0, Event::forTenant(1)->listedPublicly()->get());

        ModerationService::approve($e);
        $this->assertTrue($e->fresh()->acceptsSales());
        $this->assertCount(1, Event::forTenant(1)->listedPublicly()->get());

        // otro evento: rechazo → borrador con motivo; al corregir y publicar vuelve a revisión
        $e2 = $this->publishable();
        $e2->publish();
        ModerationService::reject($e2->fresh(), 'La descripción no es clara');
        $e2 = $e2->fresh();
        $this->assertSame(['draft', 'rejected', 'La descripción no es clara'], [$e2->status, $e2->moderation, $e2->moderation_note]);
        $e2->publish();
        $this->assertSame('pending', $e2->fresh()->moderation);
    }

    public function testSuspendedEventStopsSalesVisibilityAndEntryAndCanBeRestored(): void
    {
        $this->admin();
        $e = $this->publishable(['visibility' => 'public']);
        $e->publish();
        $t = TicketType::where('event_id', $e->id)->first();
        $tk = OrderService::place($e->fresh(), [[$t, 1]], ['name' => 'Ana', 'email' => 'a@x.com', 'phone' => '7000000'])->tickets()->first();
        $scanner = new Scanner(['name' => 'P1']); $scanner->tenant_id = 1; $scanner->save(); $scanner->events()->sync([$e->id]);

        ModerationService::suspend($e->fresh(), 'Reporte de fraude');
        $e = $e->fresh();
        $this->assertSame('suspended', $e->moderation);
        $this->assertCount(0, Event::forTenant(1)->listedPublicly()->get());
        $r = \Aero\Tickets\Classes\AccessControl::validate($tk->code, $e, \Aero\Tickets\Classes\AccessControl::scannerOperator($scanner), 'qr', $scanner);
        $this->assertSame('event_not_open', $r['result']);                               // no se permite el ingreso
        $this->assertStringContainsString('suspendido', $r['message']);
        try { $e->publish(); $this->fail('suspendido no se republica'); } catch (\ApplicationException $x) { $this->assertStringContainsString('suspendido', $x->getMessage()); }
        $this->assertSame('valid', $tk->fresh()->status);                                // la entrada no se consumió

        ModerationService::restore($e);
        $this->assertTrue($e->fresh()->acceptsSales());
        $this->assertSame('ok', \Aero\Tickets\Classes\AccessControl::validate($tk->code, $e->fresh(), \Aero\Tickets\Classes\AccessControl::scannerOperator($scanner), 'qr', $scanner)['result']);
    }

    public function testModerationOnlyChangesThroughTheService(): void
    {
        $e = $this->event();
        $e->moderation = 'suspended';
        $this->expectException(\ApplicationException::class);
        $e->save();
    }

    public function testPlatformCancellationCascadesOrdersAndMarksPaymentsForRefund(): void
    {
        $this->admin();
        $e = $this->publishable();
        $e->publish();
        $paid = TicketType::create(['event_id' => $e->id, 'name' => 'Pago', 'kind' => 'paid', 'price' => 50]);
        $free = TicketType::where('event_id', $e->id)->first();
        $o1 = OrderService::place($e->fresh(), [[$paid, 1]], ['name' => 'A', 'email' => 'a@x.com', 'phone' => '7000000']);     // sin pagar
        $o2 = OrderService::place($e->fresh(), [[$paid, 1]], ['name' => 'B', 'email' => 'b@x.com', 'phone' => '7000000']);
        PaymentService::registerManual($o2, 50.0, 'Efectivo, recibo 5', null, PaymentService::adminActor());                  // cobrada
        $o3 = OrderService::place($e->fresh(), [[$free, 2]], ['name' => 'C', 'email' => 'c@x.com', 'phone' => '7000000']);

        \Event::listen('aero.tickets.orderCancelled', fn ($o) => PaymentService::onOrderCancelled($o));
        ModerationService::cancel($e->fresh(), 'Incumplimiento de condiciones');

        $this->assertSame('cancelled', $e->fresh()->status);
        $this->assertSame(['cancelled', 'cancelled', 'cancelled'], [$o1->fresh()->status, $o2->fresh()->status, $o3->fresh()->status]);
        $this->assertSame('refund_pending', Payment::where('order_id', $o2->id)->first()->status);
        $this->assertSame(0, Ticket::where('event_id', $e->id)->whereIn('status', ['valid', 'pending', 'reserved'])->count());
        $this->assertContains('event.cancelled_by_platform', Audit::where('event_id', $e->id)->pluck('action')->all());
    }

    public function testEventChangesAreAuditedWithBeforeAndAfter(): void
    {
        $this->admin();
        $e = $this->event(['capacity' => 100]);
        $e->capacity = 150;
        $e->city = 'Sucre';
        $e->save();

        $row = Audit::where('event_id', $e->id)->where('action', 'event.updated')->first();
        $this->assertSame([100, 150], array_map('intval', $row->changes['capacity']));
        $this->assertSame(['La Paz', 'Sucre'], $row->changes['city']);
        $this->assertSame('admin', $row->actor_type);
        $this->assertNotNull(Audit::where('event_id', $e->id)->where('action', 'event.created')->first());
    }

    public function testAuditIsAppendOnly(): void
    {
        $this->admin();
        $this->event();
        $a = Audit::first();
        foreach ([fn () => $a->update(['action' => 'x']), fn () => $a->delete()] as $try) {
            try { $try(); $this->fail(); } catch (\ApplicationException $x) { $this->assertNotEmpty($x->getMessage()); }
        }
    }

    /* ---------- comisiones ---------- */

    public function testCommissionPriorityTypeThenEventThenOrganizerThenPlatform(): void
    {
        $this->admin();
        $this->setting(['default_fee_percent' => 4, 'default_fee_fixed' => 0, 'default_fee_payer' => 'buyer']);
        $e = $this->event(['tenant_id' => 1]);
        $t = TicketType::create(['event_id' => $e->id, 'name' => 'P', 'kind' => 'paid', 'price' => 100]);

        $terms = fn () => CommissionResolver::terms($t->fresh());
        $this->assertSame([4.0, 'platform'], [$terms()['percent'], $terms()['source']['percent']]);

        $r1 = CommissionRule::create(['scope' => 'tenant', 'tenant_id' => 1, 'percent' => 6, 'payer' => 'organizer']);
        CommissionResolver::flush();
        $this->assertSame([6.0, 'organizer', 'organizer', 'organizer'], [$terms()['percent'], $terms()['payer'], $terms()['source']['percent'], $terms()['source']['payer']]);

        CommissionRule::create(['scope' => 'event', 'event_id' => $e->id, 'tenant_id' => 1, 'fixed' => 2]);       // solo el fijo: el % sigue del organizador
        CommissionResolver::flush();
        $x = $terms();
        $this->assertSame([6.0, 2.0, 'organizer', 'event'], [$x['percent'], $x['fixed'], $x['source']['percent'], $x['source']['fixed']]);

        $t->fee_percent = 10;                                                                                    // el tipo de entrada manda
        $t->save();
        CommissionResolver::flush();
        $this->assertSame([10.0, 'type'], [$terms()['percent'], $terms()['source']['percent']]);

        $q = \Aero\Tickets\Classes\Pricing::quote($t->fresh());
        $this->assertEquals([100, 12, 100, 88], [$q['base'], $q['fee'], $q['total'], $q['net']]);                // 10% + Bs 2, lo paga el organizador
    }

    public function testRuleValidityDatesOneActivePerScopeAndEmptyRuleRejected(): void
    {
        $this->admin();
        $e = $this->event();
        $t = TicketType::create(['event_id' => $e->id, 'name' => 'P', 'kind' => 'paid', 'price' => 100]);
        CommissionRule::create(['scope' => 'tenant', 'tenant_id' => 1, 'percent' => 9, 'valid_until' => today()->subDay()->toDateString()]);
        CommissionResolver::flush();
        $this->assertSame('platform', CommissionResolver::terms($t)['source']['percent']);                       // vencida: no aplica

        try { CommissionRule::create(['scope' => 'tenant', 'tenant_id' => 1]); $this->fail('regla vacía'); } catch (\ApplicationException $x) { $this->assertStringContainsString('no cambia nada', $x->getMessage()); }
        CommissionRule::create(['scope' => 'tenant', 'tenant_id' => 2, 'percent' => 5]);
        $this->expectException(\ApplicationException::class);
        CommissionRule::create(['scope' => 'tenant', 'tenant_id' => 2, 'percent' => 7]);                         // ya hay una activa
    }

    public function testOrderSnapshotsCommercialTermsAndLedgerAccruesOnceAndVoidsOnRefund(): void
    {
        $this->admin();
        $this->setting(['default_fee_percent' => 5, 'default_fee_payer' => 'buyer']);
        $e = $this->event();
        $e->status = 'published'; $e->save();
        $t = TicketType::create(['event_id' => $e->id, 'name' => 'P', 'kind' => 'paid', 'price' => 100]);
        $o = OrderService::place($e, [[$t, 2]], ['name' => 'A', 'email' => 'a@x.com', 'phone' => '7000000']);

        $this->assertEquals(10, $o->commission_total);
        $terms = $o->fresh()->commission_terms;
        $this->assertSame([5.0, 'buyer', 'platform', 2], [(float) $terms[0]['percent'], $terms[0]['payer'], $terms[0]['source']['percent'], $terms[0]['qty']]);

        // la regla cambia DESPUÉS: la orden conserva lo que se aplicó
        CommissionRule::create(['scope' => 'tenant', 'tenant_id' => 1, 'percent' => 20]);
        $this->assertEquals(5, $o->fresh()->commission_terms[0]['percent']);

        $p = PaymentService::registerManual($o, 210.0, 'Efectivo, recibo 8', null, PaymentService::adminActor());
        $entry = CommissionEntry::where('order_id', $o->id)->first();
        $this->assertSame('accrued', $entry->status);
        $this->assertEquals([10, 200], [$entry->commission_amount, $entry->base_amount]);
        $this->assertNull($entry->settlement_id);

        PaymentService::approve($p->fresh(), 210.0, null, PaymentService::adminActor());          // repetir no duplica
        $this->assertSame(1, CommissionEntry::where('order_id', $o->id)->count());

        $o->fresh()->cancel('Devolución');                                                          // pago a devolver → comisión anulada
        $this->assertSame('void', $entry->fresh()->status);
    }

    public function testNoCommissionForFreeOrders(): void
    {
        $e = $this->event(); $e->status = 'published'; $e->save();
        $t = TicketType::create(['event_id' => $e->id, 'name' => 'G', 'kind' => 'free']);
        $o = OrderService::place($e, [[$t, 2]], ['name' => 'A', 'email' => 'a@x.com', 'phone' => '7000000']);
        $this->assertEquals(0, $o->commission_total);
        $this->assertNull(CommissionLedger::accrue($o));
    }

    /* ---------- compradores, ubicaciones, plantillas, límites ---------- */

    public function testBlockedBuyerCannotBuyButCanBeUnblockedAndIsAudited(): void
    {
        $this->admin();
        $e = $this->event(); $e->status = 'published'; $e->save();
        $t = TicketType::create(['event_id' => $e->id, 'name' => 'G', 'kind' => 'free']);
        OrderService::place($e, [[$t, 1]], ['name' => 'Mal Comprador', 'email' => 'Malo@X.com', 'phone' => '7000000']);
        $b = Buyer::where('email', 'malo@x.com')->first();
        $this->assertSame(1, (int) $b->orders_count);

        try { $b->block('x'); $this->fail(); } catch (\ApplicationException $x) { $this->assertStringContainsString('motivo', $x->getMessage()); }
        $b->block('Contracargos reiterados');
        try { OrderService::place($e, [[$t, 1]], ['name' => 'Mal', 'email' => 'malo@x.com', 'phone' => '7000000']); $this->fail('bloqueado'); } catch (\ApplicationException $x) { $this->assertStringContainsString('No fue posible', $x->getMessage()); }
        $this->assertSame(1, Order::count());

        $b->fresh()->unblock('Aclarado');
        $paid = TicketType::create(['event_id' => $e->id, 'name' => 'P', 'kind' => 'paid', 'price' => 10]);
        OrderService::place($e, [[$paid, 1]], ['name' => 'Mal', 'email' => 'malo@x.com', 'phone' => '7000000']);   // ya puede comprar
        $this->assertSame(['buyer.blocked', 'buyer.unblocked'], Audit::where('subject_type', 'buyer')->orderBy('id')->pluck('action')->all());
    }

    public function testLocationsValidateEventPlaceOnChangeAndProtectUsedOnes(): void
    {
        $this->admin();
        $dep = Location::firstOrCreate(['type' => 'department', 'name' => 'La Paz', 'parent_id' => null], ['is_active' => true]);
        Location::firstOrCreate(['type' => 'city', 'name' => 'El Alto', 'parent_id' => $dep->id], ['is_active' => true]);
        $cb = Location::firstOrCreate(['type' => 'city', 'name' => 'Coroico', 'parent_id' => $dep->id], ['is_active' => true]);
        $cb->is_active = false;
        $cb->save();

        $e = $this->event(['department' => 'La Paz', 'city' => 'El Alto']);
        foreach ([['city', 'Atlántida'], ['city', 'Coroico'], ['department', 'Narnia']] as [$f, $v]) {
            try { $e->{$f} = $v; $e->save(); $this->fail("$f=$v debía rechazarse"); } catch (\ApplicationException $x) { $this->assertNotEmpty($x->getMessage()); }
            $e = $e->fresh();
        }
        $this->assertContains('El Alto', Location::citiesOf('La Paz')->pluck('name')->all());
        $this->assertNotContains('Coroico', Location::citiesOf('La Paz')->pluck('name')->all());              // la desactivada no se ofrece
        $this->assertSame(['La Paz' => 'La Paz'], array_intersect_key($e->getDepartmentOptions(), ['La Paz' => 1]));

        try { Location::where('name', 'El Alto')->first()->delete(); $this->fail('en uso'); } catch (\ApplicationException $x) { $this->assertStringContainsString('eventos', $x->getMessage()); }
        try { $dep->delete(); $this->fail('con ciudades'); } catch (\ApplicationException $x) { $this->assertStringContainsString('ciudades', $x->getMessage()); }
    }

    public function testTemplatesRenderEscapedAreEditableDisableableButCriticalOnesAreNot(): void
    {
        $this->admin();
        $r = Templates::render('payment_rejected', ['buyer_name' => '<b>Ana</b>', 'event_name' => 'Show', 'reason' => 'x', 'retry_html' => '', 'order_url' => 'https://x.bo/o']);
        $this->assertStringContainsString('&lt;b&gt;Ana&lt;/b&gt;', $r['html']);                                // lo del usuario se escapa
        $this->assertStringContainsString('Show', $r['subject']);

        Template::firstOrCreate(['code' => 'payment_rejected'], ['name' => 'x', 'subject' => 's', 'body' => 'b']);
        $t = Template::where('code', 'payment_rejected')->first();
        $t->subject = 'Pago observado: {{event_name}}';
        $t->body = '<p>Hola {{buyer_name}}</p>{{retry_html}}';
        $t->save();
        $this->assertSame('Pago observado: Show', Templates::render('payment_rejected', ['event_name' => 'Show', 'buyer_name' => 'A', 'retry_html' => '<i>ok</i>'])['subject']);
        $this->assertStringContainsString('<i>ok</i>', Templates::render('payment_rejected', ['event_name' => 'S', 'buyer_name' => 'A', 'retry_html' => '<i>ok</i>'])['html']);   // _html no se escapa

        $t->is_active = false; $t->save();
        $this->assertNull(Templates::render('payment_rejected', []));                                           // desactivada: no se envía

        $crit = Template::firstOrCreate(['code' => 'ticket_delivery'], ['name' => 'e', 'subject' => 's', 'body' => 'b']);
        $crit->is_active = false; $crit->save();
        $this->assertTrue((bool) $crit->fresh()->is_active);                                                    // crítica: siempre activa
        $this->assertNotNull(Templates::render('ticket_delivery', []));
    }

    public function testPlatformLimitsAreEnforced(): void
    {
        $this->admin();
        $this->setting(['max_capacity_per_event' => 100, 'max_ticket_types_per_event' => 2, 'max_scanners_per_tenant' => 1, 'max_active_events' => 1, 'max_tickets_per_order' => 3]);

        try { $this->event(['capacity' => 500]); $this->fail('capacidad'); } catch (\ApplicationException $x) { $this->assertStringContainsString('100', $x->getMessage()); }
        $e = $this->event(['capacity' => 50]);
        TicketType::create(['event_id' => $e->id, 'name' => 'A', 'kind' => 'free']);
        TicketType::create(['event_id' => $e->id, 'name' => 'B', 'kind' => 'free']);
        try { TicketType::create(['event_id' => $e->id, 'name' => 'C', 'kind' => 'free']); $this->fail('tipos'); } catch (\ApplicationException $x) { $this->assertStringContainsString('2 tipos', $x->getMessage()); }

        $s = new Scanner(['name' => 'S1']); $s->tenant_id = 1; $s->save();
        try { $s2 = new Scanner(['name' => 'S2']); $s2->tenant_id = 1; $s2->save(); $this->fail('escáneres'); } catch (\ApplicationException $x) { $this->assertStringContainsString('escáneres', $x->getMessage()); }

        $e->status = 'published'; $e->save();
        $t = TicketType::where('event_id', $e->id)->first();
        try { OrderService::place($e, [[$t, 4]], ['name' => 'A', 'email' => 'a@x.com', 'phone' => '7000000']); $this->fail('por orden'); } catch (\ApplicationException $x) { $this->assertStringContainsString('Máximo 3', $x->getMessage()); }
    }

    public function testActiveEventsLimitBlocksPublishingAnotherOne(): void
    {
        $this->admin();
        $this->setting(['max_active_events' => 1]);
        $a = $this->publishable(); $a->publish();
        $b = $this->publishable();
        $this->expectException(\ApplicationException::class);
        $b->publish();
    }

    public function testLatePaymentPolicyCanSendLatePaymentsStraightToRefund(): void
    {
        $this->admin();
        $this->setting(['reopen_late_payments' => false]);
        $e = $this->event(); $e->status = 'published'; $e->save();
        $t = TicketType::create(['event_id' => $e->id, 'name' => 'P', 'kind' => 'paid', 'price' => 30]);
        $o = OrderService::place($e, [[$t, 1]], ['name' => 'A', 'email' => 'a@x.com', 'phone' => '7000000']);
        Order::where('id', $o->id)->update(['expires_at' => now()->subMinute()]);
        Ticket::where('order_id', $o->id)->update(['expires_at' => now()->subMinute()]);
        Order::expireLapsed();

        $p = PaymentService::registerManual($o->fresh(), 30.0, 'Pagó tarde en efectivo', null, PaymentService::adminActor());
        $this->assertSame('refund_pending', $p->status);
        $this->assertSame('expired', $o->fresh()->status);
    }

    public function testDashboardNumbersAddUp(): void
    {
        $this->admin();
        $e = $this->event(); $e->status = 'published'; $e->save();
        $free = TicketType::create(['event_id' => $e->id, 'name' => 'G', 'kind' => 'free']);
        $paid = TicketType::create(['event_id' => $e->id, 'name' => 'P', 'kind' => 'paid', 'price' => 100]);
        $this->setting(['default_fee_percent' => 10]);
        OrderService::place($e, [[$free, 2]], ['name' => 'A', 'email' => 'a@x.com', 'phone' => '7000000']);
        $o = OrderService::place($e, [[$paid, 1]], ['name' => 'B', 'email' => 'b@x.com', 'phone' => '7000000']);
        PaymentService::registerManual($o, 110.0, 'Efectivo, recibo 1', null, PaymentService::adminActor());
        $o2 = OrderService::place($e, [[$paid, 1]], ['name' => 'C', 'email' => 'c@x.com', 'phone' => '7000000']);
        PaymentService::submit(PaymentService::start($o2, 'transfer'), 'TRF-1');

        $s = \Aero\Tickets\Classes\AdminStats::overview();
        $this->assertSame([3, 1, 2], [$s['tickets']['issued'], $s['tickets']['sold'], $s['tickets']['free']]);
        $this->assertEquals(110, $s['sales']['gross']['BOB']);
        $this->assertEquals(10, $s['commissions']['accrued']['BOB']);
        $this->assertSame(1, $s['payments']['to_verify']);
        $this->assertSame(1, $s['events']['published']);
        $this->assertCount(30, $s['activity']);
        $this->assertSame(3, array_sum(array_column($s['activity'], 'orders')));
    }

    /** Un usuario con UN permiso (no superadmin) debe pasar las comprobaciones «alguno de»: hasAccess([...]) exigiría TODOS. */
    public function testRolePermissionsAreEvaluatedAsAnyOfNotAllOf(): void
    {
        $mk = function (array $perms) {
            $r = new \Backend\Models\UserRole(['name' => 'R' . uniqid(), 'code' => 'r_' . uniqid()]);
            $r->permissions = array_fill_keys($perms, 1);
            $r->save();
            $u = new \Backend\Models\User(['first_name' => 'T', 'last_name' => 'U', 'login' => 'u' . uniqid(), 'email' => uniqid() . '@x.com', 'password' => 'secret123', 'password_confirmation' => 'secret123']);
            $u->is_activated = true;
            $u->role_id = $r->id;
            $u->save();
            \BackendAuth::login(\Backend\Models\User::find($u->id));
        };

        $mk(['aero.tickets.admin.finance']);
        $this->assertTrue(\Aero\Tickets\Classes\Admin::can('finance'));
        $this->assertTrue(\Aero\Tickets\Classes\Admin::seesAllFinance());
        $this->assertFalse(\Aero\Tickets\Classes\Admin::can('events'));
        $this->assertFalse(\Aero\Tickets\Classes\Admin::can('settings'));

        $mk(['aero.tickets.admin.view']);
        $this->assertTrue(\Aero\Tickets\Classes\Admin::can('view'));
        $this->assertFalse(\Aero\Tickets\Classes\Admin::can('organizers'));

        // un administrador de organización con solo «verificar pagos» sí pasa el control de Orders/Attendees
        $mk(['aero.tickets.payments']);
        $this->assertTrue(\BackendAuth::getUser()->hasAnyAccess(['aero.tickets.payments', 'aero.tickets.admin.finance', 'aero.tickets.superadmin']));
        $this->assertFalse(\Aero\Tickets\Classes\Admin::can('finance'));
    }
}
