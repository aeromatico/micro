<?php namespace Aero\Church\Tests;

use Aero\Church\Classes\ChurchException;
use Aero\Church\Classes\ReportCatalog;
use Aero\Church\Classes\ReportExporter;
use Aero\Church\Classes\ReportService;
use Aero\Church\Models\Attendance;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\Contribution;
use Aero\Church\Models\EventRegistration;
use Aero\Church\Models\Fund;
use Aero\Church\Models\Group;
use Aero\Church\Models\GroupMember;
use Aero\Church\Models\Meeting;
use Aero\Church\Models\Member;
use Aero\Church\Models\Visit;
use PluginTestCase;

class ReportsTest extends PluginTestCase
{
    protected function member(array $o = []): Member
    {
        return Member::create($o + ['tenant_id' => 1, 'first_name' => 'Ana', 'last_name' => 'Pérez', 'status' => 'active']);
    }

    public function testMembersReportMasksMinorsAndIsTenantScoped(): void
    {
        $this->member(['phone' => '70011111', 'email' => 'ana@example.invalid']);
        $this->member(['first_name' => 'Niño', 'phone' => '70022222', 'email' => 'nino@example.invalid', 'birth_date' => now()->subYears(9)->toDateString()]);
        $this->member(['first_name' => 'OtraIglesia', 'tenant_id' => 2, 'phone' => '70099999']);

        $r = (new ReportService(1))->members();
        $dump = json_encode($r['rows']);

        $this->assertCount(2, $r['rows']);
        $this->assertStringContainsString('70011111', $dump);
        $this->assertStringNotContainsString('70022222', $dump, 'Los datos de contacto de un menor no salen en reportes');
        $this->assertStringNotContainsString('nino@example.invalid', $dump);
        $this->assertStringNotContainsString('70099999', $dump, 'Otra iglesia nunca');
        $this->assertSame(2, $r['summary']['Total']);
    }

    public function testMembersFilterByStatus(): void
    {
        $this->member();
        $this->member(['first_name' => 'Inactivo', 'status' => 'inactive']);

        $this->assertCount(1, (new ReportService(1))->members(['status' => 'inactive'])['rows']);
        $this->assertCount(2, (new ReportService(1))->members(['status' => 'nada'])['rows'], 'Estado inválido se ignora');
    }

    public function testGrowthHasTwelveMonthsAndAccumulates(): void
    {
        $this->member();
        $this->member(['first_name' => 'B']);
        $g = (new ReportService(1))->growth();

        $this->assertCount(12, $g['rows']);
        $last = end($g['rows']);
        $this->assertSame(now()->format('Y-m'), $last[0]);
        $this->assertSame(2, $last[1]);
        $this->assertSame(2, $last[2]);
    }

    public function testAttendanceCountsConfirmedAndComparesPeriods(): void
    {
        $m = $this->member();
        $e = ChurchEvent::create(['tenant_id' => 1, 'title' => 'Culto', 'starts_at' => now()->subDays(2)]);
        $reg = EventRegistration::create(['tenant_id' => 1, 'event_id' => $e->id, 'member_id' => $m->id, 'status' => 'registered', 'token' => 'tok1', 'checked_in_at' => now()->subDays(2)]);
        EventRegistration::create(['tenant_id' => 1, 'event_id' => $e->id, 'member_id' => $this->member(['first_name' => 'B'])->id, 'status' => 'registered', 'token' => 'tok2']);   // no llegó
        $g = Group::create(['tenant_id' => 1, 'name' => 'Célula', 'type' => 'cell']);
        $meet = Meeting::create(['group_id' => $g->id, 'meeting_date' => now()->subDay()->toDateString()]);
        Attendance::create(['tenant_id' => 1, 'meeting_id' => $meet->id, 'member_id' => $m->id, 'present' => true]);
        Attendance::create(['tenant_id' => 1, 'meeting_id' => $meet->id, 'member_id' => 999, 'present' => false]);
        // periodo anterior: 1 llegada
        $old = ChurchEvent::create(['tenant_id' => 1, 'title' => 'Previo', 'starts_at' => now()->subDays(12)]);
        EventRegistration::create(['tenant_id' => 1, 'event_id' => $old->id, 'member_id' => $m->id, 'status' => 'registered', 'token' => 'tok3', 'checked_in_at' => now()->subDays(12)]);

        $svc = new ReportService(1);
        $r = $svc->attendance(now()->subDays(6)->toDateString(), now()->toDateString());

        $this->assertSame(2, $r['summary']['Asistencias (periodo)']);   // 1 llegada a evento + 1 presente en reunión
        $this->assertSame(1, $r['summary']['Periodo anterior']);
        $this->assertSame('+100%', $r['summary']['Variación']);
        $this->assertCount(2, $r['rows']);
        $this->assertSame(2, $svc->attendanceTotals(now()->subDays(6)->startOfDay(), now()->endOfDay())['total']);
    }

    public function testDeltaHandlesZeroBase(): void
    {
        $this->assertNull(ReportService::delta(5, 0));
        $this->assertSame(50.0, ReportService::delta(15, 10));
        $this->assertSame(-50.0, ReportService::delta(5, 10));
    }

    public function testRangeSwapsReversedDates(): void
    {
        $r = (new ReportService(1))->attendance('2026-05-10', '2026-05-01');
        $this->assertStringContainsString('01/05/2026 – 10/05/2026', $r['summary']['Periodo']);
    }

    public function testGroupsReport(): void
    {
        $m = $this->member();
        $g = Group::create(['tenant_id' => 1, 'name' => 'Jóvenes', 'type' => 'youth']);
        GroupMember::create(['tenant_id' => 1, 'group_id' => $g->id, 'member_id' => $m->id, 'joined_at' => now()->toDateString()]);
        $meet = Meeting::create(['group_id' => $g->id, 'meeting_date' => now()->toDateString()]);
        Attendance::create(['tenant_id' => 1, 'meeting_id' => $meet->id, 'member_id' => $m->id, 'present' => true]);

        $row = (new ReportService(1))->groups()['rows'][0];
        $this->assertSame('Jóvenes', $row[0]);
        $this->assertSame(1, $row[4]);       // integrantes
        $this->assertSame(1, $row[6]);       // reuniones
        $this->assertSame('100%', $row[8]);
    }

    public function testVisitorsFunnel(): void
    {
        Visit::create(['tenant_id' => 1, 'name' => 'A', 'visit_date' => now()->toDateString(), 'status' => 'integrated', 'source' => 'qr']);
        Visit::create(['tenant_id' => 1, 'name' => 'B', 'visit_date' => now()->toDateString()]);
        Visit::create(['tenant_id' => 2, 'name' => 'Otra', 'visit_date' => now()->toDateString()]);

        $r = (new ReportService(1))->visitors(now()->startOfMonth()->toDateString(), now()->toDateString());
        $this->assertSame(2, $r['summary']['Visitantes']);
        $this->assertSame('50%', $r['summary']['Tasa de integración']);
        $this->assertSame(1, $r['summary']['Por formulario QR']);
    }

    public function testGivingSummaryHasNoNamesButDetailDoes(): void
    {
        $m = $this->member(['first_name' => 'Generoso']);
        $f = Fund::create(['tenant_id' => 1, 'name' => 'Diezmo', 'kind' => 'tithe']);
        Contribution::create(['tenant_id' => 1, 'fund_id' => $f->id, 'member_id' => $m->id, 'amount' => 100, 'currency' => 'BOB', 'date' => now()->toDateString(), 'method' => 'cash']);
        Contribution::create(['tenant_id' => 2, 'fund_id' => Fund::create(['tenant_id' => 2, 'name' => 'X', 'kind' => 'tithe'])->id, 'amount' => 999, 'currency' => 'BOB', 'date' => now()->toDateString(), 'method' => 'cash']);

        $svc = new ReportService(1);
        $sum = $svc->givingSummary(now()->startOfMonth()->toDateString(), now()->toDateString());
        $this->assertStringNotContainsString('Generoso', json_encode($sum));
        $this->assertSame([['Diezmo', 'BOB', 100.0]], $sum['rows']);

        $detail = $svc->contributions(now()->startOfMonth()->toDateString(), now()->toDateString());
        $this->assertCount(1, $detail['rows']);
        $this->assertStringContainsString('Generoso', json_encode($detail['rows']));
    }

    public function testBirthdaysExcludeMinorsAndContactData(): void
    {
        $soon = now()->addDays(2);
        $this->member(['first_name' => 'Cumple', 'last_name' => 'Pronto', 'phone' => '70055555', 'birth_date' => $soon->copy()->subYears(30)->toDateString()]);
        $this->member(['first_name' => 'Menor', 'birth_date' => $soon->copy()->subYears(8)->toDateString()]);
        $this->member(['first_name' => 'Lejano', 'birth_date' => now()->addDays(40)->subYears(30)->toDateString()]);

        $b = (new ReportService(1))->birthdays(7);
        $this->assertCount(1, $b);
        $this->assertSame('Cumple P.', $b[0]['name']);
        $this->assertStringNotContainsString('70055555', json_encode($b));
    }

    public function testDashboardGatesSensitiveBlocks(): void
    {
        $this->member();
        $svc = new ReportService(1);
        $secretary = $svc->dashboard(false, false);
        $pastor = $svc->dashboard(true, true);

        $this->assertArrayNotHasKey('giving', $secretary);
        $this->assertArrayNotHasKey('prayers_open', $secretary);
        $this->assertArrayHasKey('giving', $pastor);
        $this->assertArrayHasKey('prayers_open', $pastor);
        $this->assertSame(1, $secretary['active_members']);
        $this->assertCount(8, $secretary['weekly']);
    }

    public function testCatalogPermissionsPerRole(): void
    {
        $user = fn (array $perms) => new class($perms) {
            public function __construct(public array $p) {}
            public function hasAnyAccess(array $a): bool { return (bool) array_intersect($a, $this->p); }
        };

        $this->assertSame(['members', 'growth', 'attendance', 'groups', 'visitors'], array_keys(ReportCatalog::allowed($user(['aero.church.secretary']))));
        $this->assertArrayNotHasKey('contributions', ReportCatalog::allowed($user(['aero.church.pastor'])), 'El pastor no ve aportantes');
        $this->assertArrayHasKey('giving', ReportCatalog::allowed($user(['aero.church.pastor'])));
        $this->assertSame(['giving', 'contributions'], array_keys(ReportCatalog::allowed($user(['aero.church.treasury']))));
        $this->assertSame([], ReportCatalog::allowed($user(['aero.church.leader'])));
        $this->assertSame([], ReportCatalog::allowed(null));
    }

    public function testXlsxIsValidAndNeverExecutesFormulas(): void
    {
        $report = ['title' => 'Prueba', 'columns' => ['Nombre', 'Monto'], 'rows' => [['=HYPERLINK("http://evil","x")', 10], ['+cmd|calc', 2.5], ['@SUM(1)', 3]], 'summary' => ['Total' => 3]];
        $bytes = (new ReportExporter())->xlsx($report);

        $this->assertStringStartsWith('PK', $bytes);
        $tmp = tempnam(sys_get_temp_dir(), 'tx');
        file_put_contents($tmp, $bytes);
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        @unlink($tmp);

        $found = false;
        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                if (str_starts_with((string) $cell->getValue(), '=HYPERLINK')) {
                    $found = true;
                    $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $cell->getDataType(), 'Una fórmula del usuario es solo texto');
                }
            }
        }
        $this->assertTrue($found);
    }

    public function testPdfAndHtmlEscapeContent(): void
    {
        $report = ['title' => 'T', 'columns' => ['A'], 'rows' => [['<script>alert(1)</script>']], 'summary' => []];
        $ex = new ReportExporter();

        $this->assertStringNotContainsString('<script>', $ex->html($report));
        $this->assertStringStartsWith('%PDF', (string) $ex->pdf($report, 'Iglesia'));
    }

    public function testCatalogRejectsUnknownReport(): void
    {
        $this->expectException(ChurchException::class);
        ReportCatalog::run('nada', new ReportService(1), []);
    }
}
