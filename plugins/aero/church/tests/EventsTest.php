<?php namespace Aero\Church\Tests;

use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\EventService;
use Aero\Church\Classes\Reminders;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\Member;
use PluginTestCase;

class EventsTest extends PluginTestCase
{
    protected function event(int $tenant = 1, ?int $capacity = 2): ChurchEvent
    {
        return ChurchEvent::create(['tenant_id' => $tenant, 'title' => 'Culto', 'starts_at' => now()->addDays(2), 'capacity' => $capacity]);
    }

    protected function member(string $name, int $tenant = 1): Member
    {
        return Member::create(['tenant_id' => $tenant, 'first_name' => $name, 'status' => 'active']);
    }

    public function testCapacityWaitlistAndPromotion(): void
    {
        $svc = app(EventService::class);
        $e = $this->event();
        $a = $svc->register($e, $this->member('A'));
        $b = $svc->register($e, $this->member('B'));
        $c = $svc->register($e, $this->member('C'));

        $this->assertSame(['registered', 'registered', 'waitlist'], [$a->status, $b->status, $c->status]);

        $promoted = $svc->cancel($a);
        $this->assertSame($c->id, $promoted->id);
        $this->assertSame('registered', $c->fresh()->status);
        $this->assertSame(2, $e->fresh()->seated);
    }

    public function testNoDuplicateAndCrossTenantRejected(): void
    {
        $svc = app(EventService::class);
        $e = $this->event();
        $m = $this->member('A');
        $svc->register($e, $m);

        try {
            $svc->register($e, $m);
            $this->fail('Debió rechazar la inscripción duplicada');
        } catch (ChurchException $ex) {
            $this->assertStringContainsString('Ya está inscrito', $ex->getMessage());
        }

        $this->expectException(ChurchException::class);
        $svc->register($e, $this->member('Otra', 2));
    }

    public function testCheckInByTokenIsScopedToEvent(): void
    {
        $svc = app(EventService::class);
        $e1 = $this->event();
        $e2 = $this->event();
        $r = $svc->register($e1, $this->member('A'));

        $this->assertNotNull($svc->checkInByToken($e1, $r->token)->checked_in_at);
        $this->assertSame(1, $e1->fresh()->attended);

        $this->expectException(ChurchException::class);
        $svc->checkInByToken($e2, $r->token); // token de otro evento
    }

    public function testWalkInIgnoresCapacityAndGuestNeedsName(): void
    {
        $svc = app(EventService::class);
        $e = $this->event(1, 1);
        $svc->register($e, $this->member('A'));

        $w = $svc->walkIn($e, $this->member('B'));
        $this->assertNotNull($w->checked_in_at);
        $this->assertSame(2, $e->fresh()->seated);

        $this->expectException(ChurchException::class);
        $svc->walkIn($e, null, '  ');
    }

    public function testClosedEventRejectsRegistration(): void
    {
        $e = $this->event();
        $e->update(['registration_open' => false]);
        $this->expectException(ChurchException::class);
        app(EventService::class)->register($e, $this->member('A'));
    }

    public function testQrAndReminderText(): void
    {
        $r = app(EventService::class)->register($this->event(), $this->member('Ana'));
        $this->assertStringContainsString('<svg', $r->qrSvg());
        $this->assertStringContainsString('«Culto»', (new Reminders())->render($r->event, 'Ana'));
    }
}
