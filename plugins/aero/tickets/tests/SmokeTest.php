<?php namespace Aero\Tickets\Tests;

use Aero\Tickets\Classes\DuplicateOrderException;
use Aero\Tickets\Classes\OrderService;
use Aero\Tickets\Classes\PaymentService;
use Aero\Tickets\Classes\TicketIssuer;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\OrderItem;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Guest;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function testMigrationsCreateTables(): void
    {
        foreach (['aero_tickets_categories', 'aero_tickets_events'] as $table) {
            $this->assertTrue(\Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function testNavigationRequiresDeclaredPermissions(): void
    {
        $plugin = new \Aero\Tickets\Plugin($this->app);
        $declared = array_keys($plugin->registerPermissions());

        foreach ($plugin->registerNavigation() as $item) {
            $perms = $item['permissions'] ?? [];
            foreach ($item['sideMenu'] ?? [] as $sub) {
                $perms = array_merge($perms, $sub['permissions'] ?? []);
            }
            foreach ($perms as $perm) {
                $this->assertContains($perm, $declared);
            }
        }
    }

    public function testTenantIsolationFailsClosed(): void
    {
        Event::create(['tenant_id' => 1, 'name' => 'Fiesta A']);
        Event::create(['tenant_id' => 2, 'name' => 'Fiesta B']);

        $this->assertSame(['Fiesta A'], Event::forTenant(1)->pluck('name')->all());
        $this->assertSame(['Fiesta B'], Event::forTenant(2)->pluck('name')->all());
        $this->assertSame(0, Event::forTenant(null)->count());
    }

    public function testSlugIsUniqueAndPrivateEventsGetToken(): void
    {
        $a = Event::create(['tenant_id' => 1, 'name' => 'Cumple de Ana', 'visibility' => 'link']);
        $b = Event::create(['tenant_id' => 1, 'name' => 'Cumple de Ana']);

        $this->assertSame('cumple-de-ana', $a->slug);
        $this->assertSame('cumple-de-ana-2', $b->slug);
        $this->assertNotEmpty($a->access_token);
        $this->assertEmpty($b->access_token);
    }

    public function testPublishRequiresMinimumData(): void
    {
        $e = Event::create(['tenant_id' => 1, 'name' => 'Taller']);
        $this->assertNotEmpty($e->publishProblems());

        $this->expectException(\ApplicationException::class);
        $e->publish();
    }

    public function testEndMustBeAfterStart(): void
    {
        $this->expectException(\October\Rain\Database\ModelException::class);
        Event::create(['tenant_id' => 1, 'name' => 'X', 'starts_at' => '2030-01-02 10:00', 'ends_at' => '2030-01-01 10:00']);
    }

    protected function liveEvent(array $o = []): Event
    {
        $e = Event::create($o + ['tenant_id' => 1, 'name' => 'Evento ' . uniqid(), 'starts_at' => now()->addDay()]);
        $e->status = 'published';
        $e->save();

        return $e;
    }

    protected function type(Event $e, array $o = []): TicketType
    {
        return TicketType::create($o + ['event_id' => $e->id, 'name' => 'General', 'kind' => 'free']);
    }

    public function testCapacityIsEnforced(): void
    {
        $e = $this->liveEvent(['capacity' => 2]);
        $t = $this->type($e);
        TicketIssuer::issue($e, $t, ['name' => 'Ana'], 2);

        $this->assertSame(0, $e->available());
        $this->expectException(\ApplicationException::class);
        TicketIssuer::issue($e, $t, ['name' => 'Beto']);
    }

    public function testCancelledEventStopsSales(): void
    {
        $e = $this->liveEvent();
        $t = $this->type($e);
        $e->cancel();

        $this->expectException(\ApplicationException::class);
        TicketIssuer::issue($e, $t, ['name' => 'Ana']);
    }

    public function testApprovalRegistrationMakesPendingAndCourtesyIsNotPublic(): void
    {
        $e = $this->liveEvent(['registration' => 'approval']);
        [$tk] = TicketIssuer::issue($e, $this->type($e), ['name' => 'Ana'], 1, 'registration');
        $this->assertSame('pending', $tk->status);

        $court = $this->type($e, ['kind' => 'courtesy']);
        $this->expectException(\ApplicationException::class);
        TicketIssuer::issue($e, $court, ['name' => 'Ana'], 1, 'registration');
    }

    public function testPublicPaidIsATemporaryReservationThatExpiresAndFreesCapacity(): void
    {
        $e = $this->liveEvent(['capacity' => 1, 'reservation_minutes' => 10]);
        $paid = $this->type($e, ['kind' => 'paid', 'price' => 50]);
        [$tk] = TicketIssuer::issue($e, $paid, ['name' => 'Ana'], 1, 'registration');

        $this->assertSame('reserved', $tk->status);
        $this->assertNotNull($tk->expires_at);
        $this->assertSame(0, $e->available());
        $this->assertSame(1, \Aero\Tickets\Classes\Inventory::event($e)['reserved']);

        // vence: libera el cupo al instante, aun antes de que corra el cron
        Ticket::whereKey($tk->id)->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, $e->available());
        $this->assertSame(1, Ticket::expireLapsed());
        $this->assertSame('expired', $tk->fresh()->status);

        TicketIssuer::issue($e, $paid, ['name' => 'Beto'], 1, 'registration'); // ahora sí hay cupo
        $this->assertSame(0, $e->available());
    }

    public function testPricingBasePlusFeeAndWhoPays(): void
    {
        $e = $this->liveEvent();
        $buyer = $this->type($e, ['kind' => 'paid', 'price' => 100, 'fee_percent' => 5, 'fee_fixed' => 1, 'fee_payer' => 'buyer']);
        $org = $this->type($e, ['name' => 'Org', 'kind' => 'paid', 'price' => 100, 'fee_percent' => 5, 'fee_fixed' => 1, 'fee_payer' => 'organizer']);
        $free = $this->type($e, ['name' => 'Free']);

        $q = \Aero\Tickets\Classes\Pricing::quote($buyer);
        $this->assertEquals([100, 6, 106, 100], [$q['base'], $q['fee'], $q['total'], $q['net']]);
        $q = \Aero\Tickets\Classes\Pricing::quote($org);
        $this->assertEquals([100, 6, 100, 94], [$q['base'], $q['fee'], $q['total'], $q['net']]);
        $this->assertEquals(0, \Aero\Tickets\Classes\Pricing::quote($free)['fee']);

        [$tk] = TicketIssuer::issue($e, $buyer, ['name' => 'Ana']);
        $this->assertEquals([100, 6, 106], [$tk->base_amount, $tk->fee_amount, $tk->amount]);
    }

    public function testTypeQuotaAndEventCapacityBothApplyAndCourtesiesCount(): void
    {
        $e = $this->liveEvent(['capacity' => 5]);
        $vip = $this->type($e, ['name' => 'VIP', 'quantity' => 2]);
        $court = $this->type($e, ['name' => 'Cortesía', 'kind' => 'courtesy']);
        $gen = $this->type($e, ['name' => 'General']);

        TicketIssuer::issue($e, $vip, ['name' => 'A'], 2);
        try {
            TicketIssuer::issue($e, $vip, ['name' => 'B']);
            $this->fail('VIP debía estar agotado');
        } catch (\ApplicationException $x) {
            $this->assertStringContainsString('agotada', $x->getMessage());
        }
        TicketIssuer::issue($e, $court, ['name' => 'C'], 2);   // cortesías ocupan aforo
        $this->assertSame(1, $e->available());
        $this->assertTrue(\Aero\Tickets\Classes\Inventory::type($vip)['sold_out']);
        $this->assertSame(1, \Aero\Tickets\Classes\Inventory::type($gen)['available']);

        TicketIssuer::issue($e, $gen, ['name' => 'D']);
        $this->expectException(\ApplicationException::class);
        TicketIssuer::issue($e, $gen, ['name' => 'E']);
    }

    public function testCannotReduceCapacityOrQuantityBelowCommitted(): void
    {
        $e = $this->liveEvent(['capacity' => 10]);
        $t = $this->type($e, ['quantity' => 5]);
        TicketIssuer::issue($e, $t, ['name' => 'A'], 3);

        $t->quantity = 2;
        try { $t->save(); $this->fail('debía rechazar'); } catch (\ApplicationException $x) { $this->assertStringContainsString('comprometidas', $x->getMessage()); }

        $e->capacity = 2;
        try { $e->save(); $this->fail('debía rechazar'); } catch (\ApplicationException $x) { $this->assertStringContainsString('capacidad', $x->getMessage()); }

        $e->capacity = 3;
        $e->save();
        $this->assertSame(3, $e->fresh()->capacity);
    }

    public function testTicketsCannotBeCreatedOutsideTheIssuerNorMovedOrRevived(): void
    {
        $e = $this->liveEvent();
        $t = $this->type($e);
        try {
            Ticket::create(['event_id' => $e->id, 'ticket_type_id' => $t->id, 'holder_name' => 'X']);
            $this->fail('debía rechazar');
        } catch (\ApplicationException $x) {
            $this->assertStringContainsString('emisor', $x->getMessage());
        }

        [$tk] = TicketIssuer::issue($e, $t, ['name' => 'Ana']);
        $tk->status = 'cancelled';
        $tk->save();
        $tk->status = 'valid';
        $this->expectException(\ApplicationException::class);
        $tk->save();
    }

    public function testOrderOfSeveralTicketsSharesOrderCode(): void
    {
        $e = $this->liveEvent();
        $t = $this->type($e, ['max_per_order' => 3]);
        $tks = TicketIssuer::issue($e, $t, ['name' => 'Ana'], 3, 'registration');
        $this->assertCount(3, $tks);
        $this->assertSame(1, collect($tks)->pluck('order_code')->unique()->count());
        $this->assertSame('Ana (2/3)', $tks[1]->holder_name);

        $this->expectException(\ApplicationException::class); // más del máximo por compra
        TicketIssuer::issue($e, $t, ['name' => 'Ana'], 4, 'registration');
    }

    public function testPaidManualTicketIsPendingWithAmount(): void
    {
        $e = $this->liveEvent();
        $paid = $this->type($e, ['kind' => 'paid', 'price' => 80]);
        [$tk] = TicketIssuer::issue($e, $paid, ['name' => 'Ana']);

        $this->assertSame('pending', $tk->status);
        $this->assertEquals(80, $tk->amount);
        $this->assertSame([], $e->revenue());
    }

    public function testGroupGuestRsvpIssuesTicketsAndCanBeChanged(): void
    {
        $e = $this->liveEvent();
        $this->type($e);
        $g = Guest::create(['event_id' => $e->id, 'name' => 'Familia Pérez', 'invitation_kind' => 'family', 'companions_allowed' => 3]);

        TicketIssuer::rsvp($g, true, 9); // más que el grupo → se limita a 4
        $this->assertSame(4, $g->fresh()->confirmed_count);
        $this->assertSame(4, $e->tickets()->holding()->count());

        TicketIssuer::rsvp($g, false);
        $this->assertSame('declined', $g->fresh()->rsvp);
        $this->assertSame(0, $e->tickets()->holding()->count());
    }

    public function testCheckInIsSingleUse(): void
    {
        $e = $this->liveEvent();
        [$tk] = TicketIssuer::issue($e, $this->type($e), ['name' => 'Ana']);

        // sin backend, Ticket::visible() falla cerrado (sin tenant) → se prueba el camino atómico directo
        $won = Ticket::whereKey($tk->id)->where('status', 'valid')->update(['status' => 'used']);
        $again = Ticket::whereKey($tk->id)->where('status', 'valid')->update(['status' => 'used']);
        $this->assertSame([1, 0], [$won, $again]);
    }

    public function testCatalogExcludesPrivateEventsAndOtherTenants(): void
    {
        $pub = $this->liveEvent(['visibility' => 'public']);
        $this->liveEvent(['visibility' => 'link']);
        $this->liveEvent(['visibility' => 'invite']);
        $this->liveEvent(['visibility' => 'public', 'tenant_id' => 2]);
        Event::create(['tenant_id' => 1, 'name' => 'Borrador']);

        $this->assertSame([$pub->id], Event::forTenant(1)->listedPublicly()->pluck('id')->all());
    }

    public function testChildRecordsInheritEventTenant(): void
    {
        $e = $this->liveEvent(['tenant_id' => 7]);
        $this->assertSame(7, $this->type($e)->tenant_id);
        $this->assertSame(0, TicketType::forTenant(null)->count());
    }

    public function testStatusSyncMovesPublishedToOngoingAndFinished(): void
    {
        $now = $this->liveEvent(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]);
        $old = $this->liveEvent(['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()]);
        $fut = $this->liveEvent();

        Event::syncStatuses();

        $this->assertSame('ongoing', $now->fresh()->status);
        $this->assertSame('finished', $old->fresh()->status);
        $this->assertSame('published', $fut->fresh()->status);
    }

    public function testInvitationKindsFixCompanionsAndPartySize(): void
    {
        $e = $this->liveEvent();
        $mk = fn ($kind, $c, $n) => Guest::create(['event_id' => $e->id, 'name' => $n, 'invitation_kind' => $kind, 'companions_allowed' => $c]);

        $single = $mk('single', 5, 'A');
        $this->assertSame([0, 1], [$single->companions_allowed, $single->party_size]);
        $couple = $mk('couple', 9, 'C');
        $this->assertSame([1, 2], [$couple->companions_allowed, $couple->party_size]);
        $group = $mk('group', 6, 'D');
        $this->assertSame([6, 7], [$group->companions_allowed, $group->party_size]);
        $this->assertSame(2, $mk('family', 0, 'E')->party_size); // un grupo/familia tiene al menos 1 acompañante
    }

    public function testDuplicateGuestEmailOrWhatsappIsRejected(): void
    {
        $e = $this->liveEvent();
        Guest::create(['event_id' => $e->id, 'name' => 'Ana', 'email' => 'Ana@Mail.com', 'phone' => '70012345']);

        foreach ([['email' => 'ana@mail.com'], ['phone' => '+591 70012345']] as $dup) {
            try {
                Guest::create(['event_id' => $e->id, 'name' => 'Otra'] + $dup);
                $this->fail('debía rechazar el duplicado');
            } catch (\ApplicationException $x) {
                $this->assertStringContainsString('Ya hay un invitado', $x->getMessage());
            }
        }
    }

    public function testRsvpIsIdempotentAndEachCompanionGetsOwnTicketWithName(): void
    {
        $e = $this->liveEvent();
        $this->type($e);
        $g = Guest::create(['event_id' => $e->id, 'name' => 'Juan', 'invitation_kind' => 'couple']);

        TicketIssuer::rsvp($g, true, 2, ['Juan Pérez', 'María']);
        $codes = $g->tickets()->pluck('code')->sort()->values()->all();
        $this->assertCount(2, $codes);
        $this->assertContains('María', $g->tickets()->pluck('holder_name')->all());
        $this->assertSame(1, $g->tickets()->pluck('order_code')->unique()->count());

        TicketIssuer::rsvp($g, true, 2, ['Juan Pérez', 'María']); // doble envío
        TicketIssuer::rsvp($g, true, 2);
        $this->assertSame($codes, $g->tickets()->pluck('code')->sort()->values()->all());
        $this->assertSame(2, $e->tickets()->count());
    }

    public function testPaidInvitationCreatesReservedOrderAndPaymentConfirmsIt(): void
    {
        $e = $this->liveEvent();
        $paid = $this->type($e, ['kind' => 'paid', 'price' => 40, 'fee_percent' => 0]);
        $g = Guest::create(['event_id' => $e->id, 'name' => 'Luis', 'invitation_kind' => 'couple', 'ticket_type_id' => $paid->id]);

        TicketIssuer::rsvp($g, true, 2);
        $ts = $g->tickets()->get();
        $this->assertSame(['reserved', 'reserved'], $ts->pluck('status')->all());
        $this->assertSame(80.0, (float) $ts->sum('amount'));
        $this->assertSame(1, $ts->pluck('order_code')->unique()->count());
        $this->assertSame(2, $e->holdingCount());   // la reserva ocupa cupo
        $this->assertSame([], $e->revenue());        // aún no hay cobro
    }

    public function testCourtesyInvitationIssuesValidTicketsWithoutPayment(): void
    {
        $e = $this->liveEvent();
        $c = $this->type($e, ['kind' => 'courtesy']);
        $g = Guest::create(['event_id' => $e->id, 'name' => 'VIP', 'ticket_type_id' => $c->id]);
        TicketIssuer::rsvp($g, true);

        $this->assertSame(['valid'], $g->tickets()->pluck('status')->all());
        $this->assertEquals(0, $g->tickets()->first()->amount);
    }

    public function testRevokeInvalidatesUnusedTicketsKeepsUsedAndFlagsRefunds(): void
    {
        $e = $this->liveEvent();
        $paid = $this->type($e, ['kind' => 'paid', 'price' => 40]);
        $g = Guest::create(['event_id' => $e->id, 'name' => 'Grupo', 'invitation_kind' => 'group', 'companions_allowed' => 2, 'ticket_type_id' => $paid->id]);
        TicketIssuer::rsvp($g, true, 3);
        [$a, $b, $c] = $g->tickets()->orderBy('id')->get()->all();
        Ticket::whereKey($a->id)->update(['status' => 'valid', 'paid_at' => now()]);   // cobrada
        Ticket::whereKey($b->id)->update(['status' => 'used']);                         // ya ingresó
        // $c sigue reservada sin pagar

        $this->assertSame(2, $g->revoke('Ya no viene'));

        $this->assertSame('revoked', $g->fresh()->status);
        $this->assertSame('cancelled', $a->fresh()->status);
        $this->assertTrue((bool) $a->fresh()->refund_pending);
        $this->assertSame('used', $b->fresh()->status);
        $this->assertSame('cancelled', $c->fresh()->status);
        $this->assertFalse((bool) $c->fresh()->refund_pending);
        $this->assertSame(1, $e->holdingCount());       // el cupo de las anuladas se libera
        $this->assertSame('revoked', $g->invitation_status);

        $this->expectException(\ApplicationException::class);
        TicketIssuer::rsvp($g->fresh(), true, 1);
    }

    public function testRestoreReopensInvitationWithoutResurrectingTickets(): void
    {
        $e = $this->liveEvent();
        $this->type($e);
        $g = Guest::create(['event_id' => $e->id, 'name' => 'Ana']);
        TicketIssuer::rsvp($g, true);
        $g->revoke();
        $g->fresh()->restore();

        $g = $g->fresh();
        $this->assertSame(['active', 'pending'], [$g->status, $g->rsvp]);
        $this->assertSame(0, $g->tickets()->holding()->count());
        TicketIssuer::rsvp($g, true);
        $this->assertSame(1, $g->tickets()->holding()->count());
    }

    public function testWhatsappLinkNormalizesBolivianNumbersAndGuestWithTicketsCannotBeDeleted(): void
    {
        $e = $this->liveEvent();
        $this->type($e);
        $g = Guest::create(['event_id' => $e->id, 'name' => 'Ana', 'phone' => '7001-2345']);
        $this->assertSame('59170012345', $g->phoneDigits());
        $this->assertStringStartsWith('https://wa.me/59170012345?text=', $g->whatsapp_url);
        $this->assertStringContainsString('/invitacion/' . $g->token, urldecode($g->whatsapp_url));

        TicketIssuer::rsvp($g, true);
        $this->expectException(\ApplicationException::class);
        $g->delete();
    }

    public function testCannotShrinkInvitationBelowConfirmed(): void
    {
        $e = $this->liveEvent();
        $this->type($e);
        $g = Guest::create(['event_id' => $e->id, 'name' => 'Fam', 'invitation_kind' => 'group', 'companions_allowed' => 4]);
        TicketIssuer::rsvp($g, true, 4);

        $g = $g->fresh();
        $g->companions_allowed = 1;
        $this->expectException(\ApplicationException::class);
        $g->save();
    }

    protected function admin(): array
    {
        return ['type' => 'admin', 'id' => 1, 'name' => 'Admin de prueba'];
    }

    protected function buyer(array $o = []): array
    {
        return $o + ['name' => 'Ana Comprador', 'email' => 'ana@mail.com', 'phone' => '70011122'];
    }

    public function testMultiTypeOrderFreezesPricesAndReservesCapacity(): void
    {
        $e = $this->liveEvent(['capacity' => 10, 'reservation_minutes' => 15]);
        $gen = $this->type($e, ['name' => 'General', 'kind' => 'paid', 'price' => 100, 'fee_percent' => 5, 'fee_fixed' => 0, 'fee_payer' => 'buyer']);
        $free = $this->type($e, ['name' => 'Niño']);

        $o = OrderService::place($e, [[$gen, 2], [$free, 1]], $this->buyer());

        $this->assertSame('pending_payment', $o->status);
        $this->assertSame(3, $o->quantity);
        $this->assertEquals([200, 10, 210], [$o->subtotal, $o->fees, $o->total]);
        $this->assertNotNull($o->expires_at);
        $this->assertCount(2, OrderItem::where('order_id', $o->id)->get());
        $this->assertSame(3, $o->tickets()->count());
        $this->assertSame(7, $e->available());

        // El precio queda congelado aunque el organizador lo cambie después.
        $gen->price = 500;
        $gen->save();
        $this->assertEquals(210, $o->fresh()->total);
        $this->assertEquals(100, OrderItem::where('order_id', $o->id)->where('name', 'General')->first()->unit_price);
    }

    public function testFreeOrderConfirmsImmediately(): void
    {
        $e = $this->liveEvent();
        $o = OrderService::place($e, [[$this->type($e), 2]], $this->buyer());

        $this->assertSame('confirmed', $o->status);
        $this->assertSame(['valid', 'valid'], $o->tickets()->pluck('status')->all());
        $this->assertNull($o->expires_at);
    }

    public function testPaymentFlowVerifyThenConfirm(): void
    {
        $e = $this->liveEvent();
        $o = OrderService::place($e, [[$this->type($e, ['kind' => 'paid', 'price' => 30]), 1]], $this->buyer());
        $this->assertSame('reserved', $o->tickets()->first()->status);

        $o->markVerifying('Transf. 123');
        $o = $o->fresh();
        $this->assertSame('verifying', $o->status);
        $this->assertSame('pending', $o->tickets()->first()->status);   // ya no vence mientras se verifica
        $this->assertNull($o->tickets()->first()->expires_at);

        PaymentService::registerManual($o->fresh(), 30, 'Depósito verificado en el banco', null, $this->admin());
        $this->assertSame('confirmed', $o->fresh()->status);
        $this->assertSame('valid', $o->tickets()->first()->status);
        $this->assertNotNull($o->tickets()->first()->paid_at);
        $this->assertEquals(30, $e->revenue()['BOB']);
    }

    public function testExpiredOrderReleasesCapacityAndCannotBeConfirmed(): void
    {
        $e = $this->liveEvent(['capacity' => 1]);
        $paid = $this->type($e, ['kind' => 'paid', 'price' => 30]);
        $o = OrderService::place($e, [[$paid, 1]], $this->buyer());
        $this->assertSame(0, $e->available());

        Order::where('id', $o->id)->update(['expires_at' => now()->subMinute()]);
        Ticket::where('order_id', $o->id)->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, $e->available());                  // libera al instante
        $this->assertSame(1, Order::expireLapsed());
        $this->assertSame('expired', $o->fresh()->status);
        $this->assertSame('expired', $o->tickets()->first()->status);

        try { $o->fresh()->confirm(); $this->fail('no debía confirmarse'); } catch (\ApplicationException $x) { $this->assertStringContainsString('venció', $x->getMessage()); }
        try { $o->fresh()->markVerifying('x'); $this->fail(); } catch (\ApplicationException $x) { $this->assertStringContainsString('venció', $x->getMessage()); }

        OrderService::place($e, [[$paid, 1]], $this->buyer(['email' => 'otro@mail.com']));   // el cupo se puede volver a vender
    }

    public function testDuplicateLiveOrderIsRejectedAndDoubleSubmitReturnsSameOrder(): void
    {
        $e = $this->liveEvent();
        $paid = $this->type($e, ['kind' => 'paid', 'price' => 30]);
        $first = OrderService::place($e, [[$paid, 1]], $this->buyer(), 'public', null, ['idempotency_key' => 'k1']);

        $again = OrderService::place($e, [[$paid, 1]], $this->buyer(), 'public', null, ['idempotency_key' => 'k1']);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Order::count());

        try {
            OrderService::place($e, [[$paid, 1]], $this->buyer(['email' => 'ANA@mail.com']), 'public', null, ['idempotency_key' => 'k2']);
            $this->fail('debía rechazar la orden duplicada');
        } catch (DuplicateOrderException $x) {
            $this->assertSame($first->id, $x->existing->id);
        }
        $this->assertSame(1, $e->tickets()->count());
    }

    public function testPerTypeMaxAndAllOrNothingOnShortage(): void
    {
        $e = $this->liveEvent(['capacity' => 3]);
        $a = $this->type($e, ['name' => 'A', 'max_per_order' => 2]);
        $b = $this->type($e, ['name' => 'B']);

        try { OrderService::place($e, [[$a, 3]], $this->buyer()); $this->fail(); } catch (\ApplicationException $x) { $this->assertStringContainsString('Máximo 2', $x->getMessage()); }
        try { OrderService::place($e, [[$a, 2], [$b, 2]], $this->buyer()); $this->fail(); } catch (\ApplicationException $x) { $this->assertStringContainsString('Solo quedan 3', $x->getMessage()); }

        $this->assertSame(0, Order::count());                 // nada a medias
        $this->assertSame(0, $e->tickets()->count());
        $this->assertSame(3, $e->available());
    }

    public function testCancelledOrderFreesCapacityAndKeepsHistory(): void
    {
        $e = $this->liveEvent(['capacity' => 2]);
        $o = OrderService::place($e, [[$this->type($e, ['kind' => 'paid', 'price' => 30]), 2]], $this->buyer());
        $this->assertSame(0, $e->available());

        $o->cancel('Se arrepintió');
        $this->assertSame('cancelled', $o->fresh()->status);
        $this->assertSame(2, $e->available());
        $this->assertSame(1, Order::count());                   // el historial se conserva
        $this->assertSame(2, OrderItem::where('order_id', $o->id)->sum('quantity'));

        $this->expectException(\ApplicationException::class);
        $o->fresh()->delete();
    }

    public function testConfirmedPaidOrderCancelledFlagsRefundsButUsedTicketsBlockCancel(): void
    {
        $e = $this->liveEvent();
        $o = OrderService::place($e, [[$this->type($e, ['kind' => 'paid', 'price' => 30]), 2]], $this->buyer());
        PaymentService::registerManual($o->fresh(), 60, 'Efectivo, recibo 7', null, $this->admin());

        Ticket::where('order_id', $o->id)->orderBy('id')->first()->update(['status' => 'used']);
        try { $o->fresh()->cancel(); $this->fail('con un ingreso no se cancela'); } catch (\ApplicationException $x) { $this->assertStringContainsString('ingresó', $x->getMessage()); }

        Ticket::where('order_id', $o->id)->update(['status' => 'valid']);
        $o->fresh()->cancel();
        $this->assertSame([true, true], $o->tickets()->pluck('refund_pending')->map(fn ($v) => (bool) $v)->all());
    }

    public function testIncidentKeepsHoldAndCanBeResolved(): void
    {
        $e = $this->liveEvent(['capacity' => 1]);
        $o = OrderService::place($e, [[$this->type($e, ['kind' => 'paid', 'price' => 30]), 1]], $this->buyer());
        $o->markIncident('Monto no coincide');

        $o = $o->fresh();
        $this->assertSame('incident', $o->status);
        $this->assertSame(0, $e->available());                  // sigue reteniendo cupo
        $this->assertNull($o->expires_at);
        $this->assertSame(0, Order::expireLapsed());

        PaymentService::registerManual($o->fresh(), 30, 'Resuelto: el cliente pagó el saldo', null, $this->admin());
        $this->assertSame('confirmed', $o->fresh()->status);
    }

    public function testOrdersCannotBeWrittenOutsideTheService(): void
    {
        $e = $this->liveEvent();
        $o = OrderService::place($e, [[$this->type($e), 1]], $this->buyer());
        $o->buyer_name = 'Hacker';

        $this->expectException(\ApplicationException::class);
        $o->save();
    }

    public function testCancelledEventStopsOrdersAndRequiredExtraIsStored(): void
    {
        $e = $this->liveEvent(['buyer_extra_label' => 'Colegio', 'buyer_extra_required' => true]);
        $t = $this->type($e);
        $o = OrderService::place($e, [[$t, 1]], $this->buyer(['extra' => 'San Calixto']));
        $this->assertSame('San Calixto', $o->buyer_extra);

        $e->cancel();
        $this->expectException(\ApplicationException::class);
        OrderService::place($e, [[$t, 1]], $this->buyer(['email' => 'b@mail.com']));
    }

    public function testApprovalRegistrationOrderWaitsVerificationWithoutExpiring(): void
    {
        $e = $this->liveEvent(['registration' => 'approval']);
        $o = OrderService::place($e, [[$this->type($e), 1]], $this->buyer());

        $this->assertSame('verifying', $o->status);
        $this->assertSame(0, Order::expireLapsed());
        $o->confirm();
        $this->assertSame('valid', $o->tickets()->first()->status);
    }

    public function testRevokingGuestCancelsTheirOrder(): void
    {
        $e = $this->liveEvent();
        $paid = $this->type($e, ['kind' => 'paid', 'price' => 40]);
        $g = Guest::create(['event_id' => $e->id, 'name' => 'Luis', 'ticket_type_id' => $paid->id]);
        TicketIssuer::rsvp($g, true);
        $order = Order::where('guest_id', $g->id)->first();
        $this->assertSame('pending_payment', $order->status);

        $g->revoke();
        $this->assertSame('cancelled', $order->fresh()->status);
    }
}
