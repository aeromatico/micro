<?php namespace Aero\Church\Tests;

use Aero\Church\Classes\AnnouncementService;
use Aero\Church\Classes\Birthdays;
use Aero\Church\Classes\CareService;
use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\Recipients;
use Aero\Church\Models\Announcement;
use Aero\Church\Models\ChurchSettings;
use Aero\Church\Models\Member;
use Aero\Church\Models\Prayer;
use Aero\Church\Models\Visit;
use Aero\Church\Models\VisitContact;
use PluginTestCase;

class CareTest extends PluginTestCase
{
    protected function member(array $o = []): Member
    {
        return Member::create($o + ['tenant_id' => 1, 'first_name' => 'Ana', 'status' => 'active', 'phone' => '70000001', 'consent_messages' => true]);
    }

    public function testRecipientsRespectConsentMinorsAndDuplicatePhones(): void
    {
        $this->member(['first_name' => 'Con', 'phone' => '70000001']);
        $this->member(['first_name' => 'SinConsent', 'phone' => '70000002', 'consent_messages' => false]);
        $this->member(['first_name' => 'SinTel', 'phone' => null]);
        $this->member(['first_name' => 'Gemelo', 'phone' => '70000001']);                       // mismo teléfono
        $tutor = $this->member(['first_name' => 'Tutor', 'phone' => '70000003']);
        $this->member(['first_name' => 'Hijo', 'phone' => '70000009', 'guardian_id' => $tutor->id, 'birth_date' => now()->subYears(9)->toDateString()]);
        $sinTutor = $this->member(['first_name' => 'Huerfano', 'phone' => '70000010', 'birth_date' => now()->subYears(9)->toDateString()]);
        $this->member(['first_name' => 'OtraIglesia', 'tenant_id' => 2, 'phone' => '70000099']);

        $r = (new Recipients())->resolve(1, []);
        $phones = $r['recipients']->pluck('phone')->all();

        sort($phones);
        $this->assertSame(['70000001', '70000003'], $phones, 'Con consentimiento, un solo envío por teléfono; el menor va al tutor, nunca a su propio teléfono');
        $this->assertNotContains('70000009', $phones);
        $this->assertNotContains('70000010', $phones, 'Menor sin tutor: nadie');
        $this->assertNotContains('70000099', $phones, 'Otra iglesia: nunca');
        $this->assertSame('Tutor', $r['recipients']->firstWhere('phone', '70000003')['name'], 'El saludo es al tutor, no al menor');
        $this->assertGreaterThan(0, $r['skipped']);
    }

    public function testSegmentByGroupAndStatus(): void
    {
        $a = $this->member(['first_name' => 'A', 'phone' => '70000011']);
        $this->member(['first_name' => 'B', 'phone' => '70000012', 'status' => 'inactive']);
        $g = \Aero\Church\Models\Group::create(['tenant_id' => 1, 'name' => 'G', 'type' => 'cell']);
        \Aero\Church\Models\GroupMember::create(['tenant_id' => 1, 'group_id' => $g->id, 'member_id' => $a->id]);

        $this->assertCount(1, (new Recipients())->resolve(1, ['group_id' => $g->id])['recipients']);
        $this->assertCount(1, (new Recipients())->resolve(1, [])['recipients'], 'Por defecto solo activos y nuevos');
        $this->assertCount(2, (new Recipients())->resolve(1, ['statuses' => ['active', 'inactive']])['recipients']);
    }

    public function testAnnouncementSendsOnceAndIsLockedAfterwards(): void
    {
        $this->member(['phone' => '70000021']);
        $a = Announcement::create(['tenant_id' => 1, 'title' => 'Culto', 'body' => 'Hola {nombre}', 'kind' => 'announcement', 'audience' => []]);
        $svc = app(AnnouncementService::class);

        $this->assertSame(['recipients' => 1, 'skipped' => 0], $svc->preview($a));

        $svc->queue($a);                       // cola «sync» en pruebas
        $a = $a->fresh();
        $this->assertSame('sent', $a->status);
        $this->assertSame(1, $a->recipients_count);
        $this->assertSame(1, $a->recipients()->count(), 'Queda el historial por destinatario');

        try {
            $svc->queue($a);
            $this->fail('No debe poder enviarse dos veces');
        } catch (ChurchException $e) {
            $this->assertStringContainsString('ya se envió', $e->getMessage());
        }

        $a->body = 'Otro texto';
        $this->expectException(\ApplicationException::class);
        $a->save();
    }

    public function testVisitRegistrationValidationAndIntegration(): void
    {
        $svc = app(CareService::class);

        foreach ([['name' => ''], ['name' => 'X', 'phone' => '7', 'consent_data' => false], ['name' => 'X', 'consent_data' => true]] as $bad) {
            try {
                $svc->registerVisit(1, $bad);
                $this->fail('Debió rechazar: ' . json_encode($bad));
            } catch (ChurchException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }

        $v = $svc->registerVisit(1, ['name' => 'María López Peña', 'phone' => '+591 7000-0033', 'consent_data' => true, 'consent_messages' => true]);
        $this->assertSame('qr', $v->source);
        $this->assertSame('+59170000033', $v->phone);

        VisitContact::create(['tenant_id' => 1, 'visit_id' => $v->id, 'channel' => 'call', 'next_action_date' => now()->addDays(3)->toDateString()]);
        $this->assertSame('contacted', $v->fresh()->status, 'El primer contacto lo pasa de nuevo a contactado');

        $m = $svc->integrate($v);
        $this->assertSame('María', $m->first_name);
        $this->assertSame('López Peña', $m->last_name);
        $this->assertSame('new', $m->status);
        $this->assertTrue((bool) $m->consent_messages);
        $this->assertSame('integrated', $v->fresh()->status);

        $this->expectException(ChurchException::class);
        $svc->integrate($v->fresh());
    }

    public function testVisitStats(): void
    {
        $svc = app(CareService::class);
        $a = $svc->registerVisit(1, ['name' => 'A', 'phone' => '1', 'consent_data' => true]);
        $svc->registerVisit(1, ['name' => 'B', 'phone' => '2', 'consent_data' => true]);
        $svc->integrate($a);
        $svc->registerVisit(2, ['name' => 'OtraIglesia', 'phone' => '3', 'consent_data' => true]);

        $s = $svc->visitStats(now()->startOfYear()->toDateString(), now()->toDateString(), 1);
        $this->assertSame(2, $s['total']);
        $this->assertSame(1, $s['integrated']);
        $this->assertSame(50.0, $s['rate']);
    }

    public function testPrayerRegistrationIsPrivateByDefaultAndNeedsConsent(): void
    {
        $svc = app(CareService::class);
        $p = $svc->registerPrayer(1, ['request' => 'Por mi familia', 'consent_data' => true, 'is_private' => true]);
        $this->assertTrue($p->is_private);
        $this->assertSame('web', $p->source);
        $this->assertNull($p->phone);

        $this->expectException(ChurchException::class);
        $svc->registerPrayer(1, ['request' => 'x', 'consent_data' => false]);
    }

    public function testPrayerContactRequiresPhone(): void
    {
        $this->expectException(ChurchException::class);
        app(CareService::class)->registerPrayer(1, ['request' => 'x', 'consent_data' => true, 'wants_contact' => true]);
    }

    public function testPrayerStatusAnsweredStampsDate(): void
    {
        $p = Prayer::create(['tenant_id' => 1, 'request' => 'x']);
        $this->assertNull($p->answered_at);
        $p->update(['status' => 'answered']);
        $this->assertNotNull($p->fresh()->answered_at);
        $p->update(['status' => 'open']);
        $this->assertNull($p->fresh()->answered_at);
    }

    public function testBirthdaysOnlyAdultsOptedInAndOncePerDay(): void
    {
        $day = now()->setDate(2026, 5, 10);
        ChurchSettings::forTenant(1)->update(['birthday_enabled' => true]);
        ChurchSettings::forTenant(2)->update(['birthday_enabled' => false]);

        $this->member(['first_name' => 'Adulto', 'phone' => '70000041', 'birth_date' => '1990-05-10']);
        $this->member(['first_name' => 'Niño', 'phone' => '70000042', 'birth_date' => '2018-05-10']);
        $this->member(['first_name' => 'OtroDia', 'phone' => '70000043', 'birth_date' => '1990-05-11']);
        $this->member(['first_name' => 'OtraIglesia', 'tenant_id' => 2, 'phone' => '70000044', 'birth_date' => '1990-05-10']);

        (new Birthdays())->run($day);
        (new Birthdays())->run($day);   // segunda corrida el mismo día: no repite

        $a = Announcement::where('kind', 'birthday')->get();
        $this->assertCount(1, $a);
        $this->assertSame(1, $a[0]->tenant_id);
        $this->assertSame(['70000041'], $a[0]->recipients()->pluck('phone')->all());
    }
}
