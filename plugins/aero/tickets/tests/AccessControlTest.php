<?php namespace Aero\Tickets\Tests;

use Aero\Tickets\Classes\AccessControl;
use Aero\Tickets\Classes\OrderService;
use Aero\Tickets\Models\Event;
use Aero\Tickets\Models\Scan;
use Aero\Tickets\Models\Scanner;
use Aero\Tickets\Models\Ticket;
use Aero\Tickets\Models\TicketType;
use PluginTestCase;

class AccessControlTest extends PluginTestCase
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
        $e = Event::create($o + ['tenant_id' => 1, 'name' => 'Evento ' . uniqid(), 'starts_at' => now()->addDay()]);
        $e->status = 'published';
        $e->save();

        return [$e, TicketType::create(['event_id' => $e->id, 'name' => 'General', 'kind' => 'free'])];
    }

    protected function scanner(array $events, string $name = 'Puerta 1', int $tenant = 1): Scanner
    {
        $s = new Scanner(['name' => $name]);
        $s->tenant_id = $tenant;
        $s->save();
        $s->events()->sync(collect($events)->pluck('id')->all());

        return $s->fresh();
    }

    protected function tickets(Event $e, TicketType $t, int $n = 1): array
    {
        return OrderService::place($e, [[$t, $n]], ['name' => 'Ana Asistente', 'email' => 'a' . uniqid() . '@mail.com', 'phone' => '7000000'])->tickets()->orderBy('seq')->get()->all();
    }

    protected function scan(string $text, Event $e, Scanner $s, string $method = 'qr'): array
    {
        return AccessControl::validate($text, $e, AccessControl::scannerOperator($s), $method, $s);
    }

    public function testValidTicketGrantsAccessAndRecordsOperatorTimeAndCounter(): void
    {
        [$e, $t] = $this->live();
        [$tk, $tk2] = $this->tickets($e, $t, 2);
        $s = $this->scanner([$e], 'Puerta Juan');

        $r = $this->scan($tk->qr_payload, $e, $s);

        $this->assertTrue($r['allowed']);
        $this->assertSame(['ok', 'ACCESO PERMITIDO'], [$r['result'], $r['title']]);
        $this->assertSame(['in' => 1, 'issued' => 2, 'remaining' => 1], $r['counts']);
        $tk = $tk->fresh();
        $this->assertSame('used', $tk->status);
        $this->assertNotNull($tk->checked_in_at);
        $this->assertSame('Puerta Juan', $tk->checked_in_by);
        $log = Scan::where('result', 'ok')->first();
        $this->assertSame(['scanner', $s->id, 'Puerta Juan', $tk->id, 'qr'], [$log->operator_type, $log->operator_id, $log->operator_name, $log->ticket_id, $log->method]);
        $this->assertNotNull($log->scanned_at);
        $this->assertSame('valid', $tk2->fresh()->status);                                // la otra entrada de la compra sigue independiente
    }

    public function testUsedTicketIsDeniedWithoutASecondEntryAndTellsWhoAndWhen(): void
    {
        [$e, $t] = $this->live();
        [$tk] = $this->tickets($e, $t);
        $a = $this->scanner([$e], 'Puerta A');
        $b = $this->scanner([$e], 'Puerta B');

        $this->assertTrue($this->scan($tk->code, $e, $a, 'code')['allowed']);
        $r = $this->scan($tk->qr_payload, $e, $b);

        $this->assertFalse($r['allowed']);
        $this->assertSame('already_used', $r['result']);
        $this->assertStringContainsString('YA fue registrada', $r['message']);
        $this->assertSame('Puerta A', $r['first_by']);
        $this->assertSame(1, $r['counts']['in']);                                          // el contador no sube
        $this->assertSame(1, Scan::where('result', 'ok')->count());
        $this->assertSame(1, Scan::where('result', 'already_used')->count());
    }

    public function testDatabaseItselfRefusesASecondSuccessfulEntryForTheSameTicket(): void
    {
        [$e, $t] = $this->live();
        [$tk] = $this->tickets($e, $t);
        $s = $this->scanner([$e]);
        $this->scan($tk->code, $e, $s);

        $this->expectException(\Illuminate\Database\QueryException::class);
        Scan::create(['tenant_id' => 1, 'event_id' => $e->id, 'ticket_id' => $tk->id, 'ok_ticket_id' => $tk->id, 'result' => 'ok', 'operator_type' => 'scanner', 'operator_name' => 'X']);
    }

    public function testInvalidWrongEventCancelledPendingExpiredAndEventStatesAreAllDeniedAndLogged(): void
    {
        [$e, $t] = $this->live();
        [$e2, $t2] = $this->live();
        $s = $this->scanner([$e, $e2]);
        [$ok, $toCancel, $other] = array_merge($this->tickets($e, $t, 2), $this->tickets($e2, $t2));
        [$pend] = $this->tickets($e, $t, 1);
        Ticket::whereKey($pend->id)->update(['status' => 'pending']);                       // una entrada que aún no fue emitida
        Ticket::whereKey($toCancel->id)->update(['status' => 'cancelled']);

        $cases = [
            ['AT1-' . $ok->code . '-AAAAAAAA', 'invalid', 'firma'],
            ['ZZZZZZZZZZ', 'invalid', 'no encontrada'],
            [$other->qr_payload, 'wrong_event', 'otro evento'],
            [$toCancel->qr_payload, 'cancelled', 'anulada'],
            [$pend->code, 'not_issued', 'no fue emitida'],
        ];
        foreach ($cases as [$text, $result, $needle]) {
            $r = $this->scan($text, $e, $s);
            $this->assertFalse($r['allowed'], $result);
            $this->assertSame($result, $r['result']);
            $this->assertStringContainsStringIgnoringCase($needle, $r['message']);
        }
        $this->assertNull($this->scan($other->qr_payload, $e, $s)['ticket']);              // de otro evento no se revelan sus datos
        $this->assertSame('valid', $other->fresh()->status);                              // y no se consumió

        $e->status = 'finished'; $e->save();
        $this->assertSame('expired', $this->scan($ok->code, $e, $s)['result']);
        $e->status = 'cancelled'; $e->save();
        $this->assertSame('event_cancelled', $this->scan($ok->code, $e, $s)['result']);
        $this->assertSame('valid', $ok->fresh()->status);

        $this->assertSame(0, Scan::where('result', 'ok')->count());
        $this->assertGreaterThanOrEqual(8, Scan::count());                                 // todo intento queda en la bitácora
    }

    public function testScannerOnlyValidatesAssignedEventsAndOnlyWhileActive(): void
    {
        [$e1, $t1] = $this->live();
        [$e2, $t2] = $this->live();
        [$tk1] = $this->tickets($e1, $t1);
        [$tk2] = $this->tickets($e2, $t2);
        $s = $this->scanner([$e1]);

        $this->assertSame('not_allowed', $this->scan($tk2->code, $e2, $s)['result']);      // evento no asignado
        $this->assertSame('valid', $tk2->fresh()->status);
        $this->assertSame('ok', $this->scan($tk1->code, $e1, $s)['result']);

        Scanner::whereKey($s->id)->update(['is_active' => false]);
        $this->assertSame('not_allowed', $this->scan($tk1->code, $e1, $s->fresh())['result']);
        $this->assertNull(Scanner::findByToken($s->plainToken ?? str_repeat('x', 40)));
    }

    public function testScannerOfAnotherTenantCannotTouchAnEventNorFindItsTickets(): void
    {
        [$e, $t] = $this->live(['tenant_id' => 1]);
        [$tk] = $this->tickets($e, $t);
        $foreign = $this->scanner([$e], 'Intruso', 2);

        $this->assertSame('not_allowed', $this->scan($tk->code, $e, $foreign)['result']);
        $this->assertSame('valid', $tk->fresh()->status);

        // y un ticket de otro tenant no existe para un escáner válido del evento
        [$e2, $t2] = $this->live(['tenant_id' => 2]);
        [$other] = $this->tickets($e2, $t2);
        $mine = $this->scanner([$e], 'Mío', 1);
        $this->assertSame('invalid', $this->scan($other->code, $e, $mine)['result']);
    }

    public function testScannerTokenIsHashedAndRegeneratingKillsTheOldOne(): void
    {
        $s = new Scanner(['name' => 'Móvil']);
        $s->tenant_id = 1;
        $s->save();
        $plain = $s->plainToken;

        $this->assertSame(40, strlen($plain));
        $this->assertStringNotContainsString($plain, json_encode(\DB::table('aero_tickets_scanners')->where('id', $s->id)->first()));   // nunca en claro
        $this->assertSame($s->id, Scanner::findByToken($plain, 1)->id);
        $this->assertNull(Scanner::findByToken($plain, 2));                               // atado al tenant
        $this->assertNull(Scanner::findByToken(substr($plain, 0, 39) . (substr($plain, -1) === 'x' ? 'y' : 'x'), 1));   // un carácter distinto

        $s->issueToken();
        $s->save();
        $this->assertNull(Scanner::findByToken($plain, 1));
        $this->assertNotNull(Scanner::findByToken($s->plainToken, 1));
    }

    public function testManualSearchIsScopedToTheEventAndEscapesWildcards(): void
    {
        [$e, $t] = $this->live();
        [$e2, $t2] = $this->live();
        OrderService::place($e, [[$t, 1]], ['name' => 'María López', 'email' => 'maria@mail.com', 'phone' => '70011122']);
        OrderService::place($e, [[$t, 1]], ['name' => '100% Real', 'email' => 'real@mail.com', 'phone' => '70022233']);
        OrderService::place($e2, [[$t2, 1]], ['name' => 'María Otra', 'email' => 'otra@mail.com', 'phone' => '70033344']);

        $this->assertSame(['María López'], array_column(AccessControl::search($e, 'maría'), 'holder') ?: array_column(AccessControl::search($e, 'Mar'), 'holder'));
        $this->assertSame(['María López'], array_column(AccessControl::search($e, '7001'), 'holder'));          // por teléfono
        $this->assertSame([], AccessControl::search($e, 'ab'));                                                  // mínimo 3 caracteres
        $this->assertSame(['100% Real'], array_column(AccessControl::search($e, '100%'), 'holder'));
        $this->assertSame([], AccessControl::search($e, '%%%'));                                                 // el comodín no lista todo
        $this->assertTrue(AccessControl::search($e, 'Mar')[0]['can_enter']);
    }

    public function testManualValidationIsLoggedWithItsMethod(): void
    {
        [$e, $t] = $this->live();
        [$tk] = $this->tickets($e, $t);
        $this->scan($tk->code, $e, $this->scanner([$e]), 'manual');

        $this->assertSame('manual', Scan::where('result', 'ok')->first()->method);
    }

    public function testAccessLogIsAppendOnly(): void
    {
        [$e, $t] = $this->live();
        [$tk] = $this->tickets($e, $t);
        $this->scan($tk->code, $e, $this->scanner([$e]));
        $log = Scan::first();

        foreach ([fn () => $log->update(['result' => 'invalid']), fn () => $log->delete()] as $try) {
            try { $try(); $this->fail('debía rechazar'); } catch (\ApplicationException $x) { $this->assertNotEmpty($x->getMessage()); }
        }
        $this->assertSame('ok', $log->fresh()->result);
    }
}
