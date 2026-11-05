<?php namespace Aero\Tickets\Tests;

use Aero\Tickets\Classes\AccessLink;
use Aero\Tickets\Classes\OrderService;
use Aero\Tickets\Classes\PaymentService;
use Aero\Tickets\Classes\TicketIssuer;
use Aero\Tickets\Classes\TicketRenderer;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Guest;
use Aero\Tickets\Models\Order;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use PluginTestCase;

class TicketsQrTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        \Aero\Tickets\Models\Settings::clearInternalCache();
        \Aero\Tickets\Classes\CommissionResolver::flush();
        \Aero\Tickets\Classes\OrganizerService::flush();
    }

    protected function live(array $o = []): array
    {
        $e = Event::create($o + ['tenant_id' => 1, 'name' => 'Evento ' . uniqid(), 'starts_at' => now()->addDay(), 'venue_name' => 'Sala', 'city' => 'La Paz']);
        $e->status = 'published';
        $e->save();

        return [$e, TicketType::create(['event_id' => $e->id, 'name' => 'General', 'kind' => 'free'])];
    }

    protected function buyer(): array
    {
        return ['name' => 'Ana Pérez Sensible', 'email' => 'ana.sensible@mail.com', 'phone' => '70099988', 'document' => '1234567'];
    }

    public function testCodesAreUniqueUnambiguousAndTokensAreSeparateSecrets(): void
    {
        [$e, $t] = $this->live();
        $tickets = OrderService::place($e, [[$t, 5]], $this->buyer())->tickets()->get();

        $this->assertCount(5, $tickets->pluck('code')->unique());
        $this->assertCount(5, $tickets->pluck('public_token')->unique());
        foreach ($tickets as $tk) {
            $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{10}$/', $tk->code);      // sin 0/O/1/I
            $this->assertSame(40, strlen($tk->public_token));
            $this->assertNotSame($tk->code, $tk->public_token);                               // ver la entrada ≠ ingresar con ella
        }
    }

    public function testQrIsSignedAndCarriesNoPersonalData(): void
    {
        [$e, $t] = $this->live();
        $tk = OrderService::place($e, [[$t, 1]], $this->buyer())->tickets()->first();
        $payload = $tk->qr_payload;

        $this->assertMatchesRegularExpression('/^AT1-[A-Z0-9]{10}-[A-Z0-9]{8}$/', $payload);
        foreach (['Ana', 'Sensible', 'sensible@mail', '70099988', '1234567'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $payload);
        }
        $this->assertSame($tk->code, Ticket::parseScan($payload));
        $this->assertSame($tk->code, Ticket::parseScan(strtolower($payload)));               // lectores que cambian mayúsculas
        $this->assertSame($tk->code, Ticket::parseScan($tk->code));                         // código escrito a mano
        $this->assertSame($tk->code, Ticket::parseScan(substr($tk->code, 0, 5) . ' - ' . substr($tk->code, 5)));
    }

    public function testStatesMapToIssuedUsedCancelledExpiredAndPending(): void
    {
        [$e, $t] = $this->live();
        $tk = OrderService::place($e, [[$t, 1]], $this->buyer())->tickets()->first();
        $this->assertSame('issued', $tk->state);
        Ticket::whereKey($tk->id)->update(['status' => 'used']);
        $this->assertSame(['used', 'Utilizada'], [$tk->fresh()->state, $tk->fresh()->state_label]);
        Ticket::whereKey($tk->id)->update(['status' => 'cancelled']);
        $this->assertSame('cancelled', $tk->fresh()->state);
        Ticket::whereKey($tk->id)->update(['status' => 'expired']);
        $this->assertSame('expired', $tk->fresh()->state);
        Ticket::whereKey($tk->id)->update(['status' => 'reserved']);
        $this->assertSame('pending', $tk->fresh()->state);
    }

    public function testSerialNumbersAreUniquePerOrderAtDatabaseLevelAndReopenContinuesThem(): void
    {
        [$e, $t] = $this->live(['capacity' => 10]);
        $paid = TicketType::create(['event_id' => $e->id, 'name' => 'Pago', 'kind' => 'paid', 'price' => 25]);
        $o = OrderService::place($e, [[$paid, 2]], $this->buyer());
        $this->assertSame([1, 2], $o->tickets()->orderBy('seq')->pluck('seq')->all());

        // un número repetido en la misma orden lo rechaza la base, no solo el código
        try {
            \DB::table('aero_tickets_tickets')->where('id', $o->tickets()->first()->id)->update(['seq' => 2]);
            $this->fail('debía violar la restricción única');
        } catch (\Illuminate\Database\QueryException $x) {
            $this->assertStringContainsString('UNIQUE', strtoupper($x->getMessage()));
        }

        // vence y el pago llega tarde: las nuevas entradas continúan la numeración (no repiten 1 y 2)
        Order::where('id', $o->id)->update(['expires_at' => now()->subMinute()]);
        Ticket::where('order_id', $o->id)->update(['expires_at' => now()->subMinute()]);
        Order::expireLapsed();
        PaymentService::registerManual($o->fresh(), 50.0, 'Pago tardío en efectivo', null, ['type' => 'admin', 'id' => 1, 'name' => 'Admin']);

        $this->assertSame([3, 4], Ticket::where('order_id', $o->id)->where('status', 'valid')->orderBy('seq')->pluck('seq')->all());
        $this->assertSame(4, Ticket::where('order_id', $o->id)->count());
    }

    public function testRepeatedPaymentConfirmationNeverIssuesAgain(): void
    {
        [$e] = $this->live();
        $paid = TicketType::create(['event_id' => $e->id, 'name' => 'Pago', 'kind' => 'paid', 'price' => 25]);
        $o = OrderService::place($e, [[$paid, 2]], $this->buyer());
        $p = PaymentService::registerManual($o, 50.0, 'Efectivo, recibo 1', null, ['type' => 'admin', 'id' => 1, 'name' => 'Admin']);
        $codes = Ticket::where('order_id', $o->id)->pluck('code')->sort()->values()->all();

        PaymentService::approve($p->fresh(), 50.0, null, ['type' => 'admin', 'id' => 2, 'name' => 'Otro']);
        $o->fresh()->tickets()->count();
        $this->assertSame($codes, Ticket::where('order_id', $o->id)->pluck('code')->sort()->values()->all());
        $this->assertSame(2, Ticket::where('order_id', $o->id)->count());
    }

    public function testGuestTicketsKeepInvitationOriginAndOneTicketPerAttendee(): void
    {
        [$e, $t] = $this->live();
        $g = Guest::create(['event_id' => $e->id, 'name' => 'Familia', 'invitation_kind' => 'family', 'companions_allowed' => 2]);
        TicketIssuer::rsvp($g, true, 3, ['Papá', 'Mamá', 'Hijo']);

        $tks = $g->tickets()->orderBy('seq')->get();
        $this->assertSame(['Papá', 'Mamá', 'Hijo'], $tks->pluck('holder_name')->all());
        $this->assertCount(3, $tks->pluck('code')->unique());
        $this->assertSame('Invitación', TicketRenderer::details($tks[0])['origin']);
        $this->assertSame('Entrada 2 de 3', TicketRenderer::details($tks[1])['serial']);
    }

    public function testPdfHasOnePagePerTicketAndHidesQrForNonIssuedOnes(): void
    {
        $this->assertTrue(TicketRenderer::pdfAvailable());
        [$e, $t] = $this->live(['requirements' => 'Traer CI', 'min_age' => 18, 'address_reference' => 'Frente a la plaza']);
        $tks = OrderService::place($e, [[$t, 3]], $this->buyer())->tickets()->orderBy('seq')->get();
        Ticket::whereKey($tks[2]->id)->update(['status' => 'cancelled']);

        $html = TicketRenderer::pdfHtml(Ticket::whereIn('id', $tks->pluck('id'))->orderBy('seq')->get());
        $this->assertSame(3, substr_count($html, 'class="page"'));
        foreach (['Traer CI', 'Edad mínima: 18', 'Frente a la plaza', $e->name, $tks[0]->code] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $this->assertSame(2, substr_count($html, 'data:image/svg+xml'));                            // la anulada no muestra QR
        $this->assertStringContainsString('NO VÁLIDA', $html);

        $pdf = TicketRenderer::pdf(Ticket::whereIn('id', $tks->pluck('id'))->orderBy('seq')->get());
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function testMyTicketsLinkIsSignedExpiringAndBoundToTenantAndEmail(): void
    {
        $q = AccessLink::params('Ana@Mail.com', 5, 60);
        $this->assertSame('ana@mail.com', AccessLink::verify($q, 5));

        $this->assertNull(AccessLink::verify($q, 6));                                           // otro espacio de trabajo
        $this->assertNull(AccessLink::verify(['s' => 'x'] + $q, 5));                            // firma alterada
        $this->assertNull(AccessLink::verify(['e' => rtrim(strtr(base64_encode('otro@mail.com'), '+/', '-_'), '=')] + $q, 5));   // otro correo
        $this->assertNull(AccessLink::verify(['x' => time() - 1] + $q, 5));                     // vencido
        $this->assertNull(AccessLink::verify([], 5));
    }
}
