<?php namespace Aero\School\Tests;

use Aero\School\Classes\AttendanceService;
use Aero\School\Classes\Billing;
use Aero\School\Classes\Collections;
use Aero\School\Classes\Curriculum;
use Aero\School\Classes\GradeBook;
use Aero\School\Classes\Notifier;
use Aero\School\Classes\ReportCard;
use Aero\School\Classes\Reports;
use Aero\School\Classes\SchoolException;
use Aero\School\Models\Charge;
use Aero\School\Models\Course;
use Aero\School\Models\FeePlan;
use Aero\School\Models\Guardian;
use Aero\School\Models\Settings;
use Aero\School\Models\Student;
use Aero\School\Models\Subject;
use Aero\School\Models\Year;
use PluginTestCase;

class SmokeTest extends PluginTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Notifier::$muted = true;
        Notifier::$log = [];
    }

    protected function course(int $tenant, string $name = '1ro A', string $level = 'primaria'): Course
    {
        $year = Year::firstOrCreate(['tenant_id' => $tenant, 'name' => '2026'], ['starts_on' => '2026-02-02', 'is_current' => true]);

        return Course::create(['tenant_id' => $tenant, 'year_id' => $year->id, 'name' => $name, 'level' => $level, 'grade' => 1]);
    }

    protected function student(Course $course, string $first = 'Ana', ?string $phone = '59170000001'): Student
    {
        $s = Student::create(['tenant_id' => $course->tenant_id, 'course_id' => $course->id, 'first_name' => $first, 'paternal_surname' => 'Pérez', 'status' => 'active']);
        $g = Guardian::create(['tenant_id' => $course->tenant_id, 'name' => 'Madre de ' . $first, 'phone' => $phone]);
        $s->guardians()->attach($g->id, ['is_primary' => true, 'is_financial' => true]);

        return $s;
    }

    protected function plan(Course $course, int $installments = 3): FeePlan
    {
        return FeePlan::create(['tenant_id' => $course->tenant_id, 'year_id' => $course->year_id, 'name' => 'Pensión', 'kind' => 'pension',
            'amount' => 100, 'installments' => $installments, 'first_month' => 2, 'due_day' => 10]);
    }

    public function testGenerateChargesIsIdempotentAndAppliesDiscount(): void
    {
        $course = $this->course(1);
        $s = $this->student($course);
        $s->update(['discount_pct' => 50]);
        $plan = $this->plan($course);

        $this->assertSame(3, app(Billing::class)->generate($plan));
        $this->assertSame(0, app(Billing::class)->generate($plan));
        $c = Charge::where('student_id', $s->id)->orderBy('due_on')->first();
        $this->assertSame('2026-02', $c->period);
        $this->assertEquals(50.0, $c->discount);
        $this->assertEquals(50.0, $c->due);
    }

    public function testPaymentAllocatesOldestFirstAndPartial(): void
    {
        $course = $this->course(1);
        $s = $this->student($course);
        app(Billing::class)->generate($this->plan($course));

        $p = app(Billing::class)->recordPayment($s, 150, 'cash');
        $this->assertSame(1, $p->receipt_no);
        $charges = Charge::where('student_id', $s->id)->orderBy('due_on')->get();
        $this->assertSame('paid', $charges[0]->status);
        $this->assertSame('partial', $charges[1]->status);
        $this->assertEquals(50.0, $charges[1]->due);
        $this->assertEquals(150.0, $s->fresh()->payments()->sum('amount'));
        $this->assertEquals(150.0, $s->pendingBalance());
    }

    public function testCannotOverpayAndVoidRestoresCharges(): void
    {
        $course = $this->course(1);
        $s = $this->student($course);
        app(Billing::class)->generate($this->plan($course, 1));

        try {
            app(Billing::class)->recordPayment($s, 500, 'cash');
            $this->fail('Debió rechazar el sobrepago');
        } catch (SchoolException $e) {
            $this->assertStringContainsString('supera', $e->getMessage());
        }

        $p = app(Billing::class)->recordPayment($s, 100, 'cash');
        $this->assertSame('paid', Charge::where('student_id', $s->id)->first()->status);
        app(Billing::class)->voidPayment($p);
        $c = Charge::where('student_id', $s->id)->first();
        $this->assertSame('pending', $c->status);
        $this->assertEquals(0.0, (float) $c->paid_amount);
    }

    public function testReceiptNumbersAreSequentialPerTenant(): void
    {
        $a = $this->student($this->course(1));
        $b = $this->student($this->course(2, '1ro B'), 'Luis');
        app(Billing::class)->generate($this->plan($a->course, 1));
        app(Billing::class)->generate($this->plan($b->course, 1));

        $this->assertSame(1, app(Billing::class)->recordPayment($a, 100)->receipt_no);
        $this->assertSame(1, app(Billing::class)->recordPayment($b, 100)->receipt_no);
    }

    public function testAttendanceNotifiesAbsenceOnlyOnce(): void
    {
        $course = $this->course(1);
        $s = $this->student($course);
        $svc = app(AttendanceService::class);

        $r = $svc->record($course, '2026-03-02', [$s->id => ['status' => 'absent']]);
        $this->assertSame(1, $r['notified']);
        $this->assertCount(1, Notifier::$log);

        $svc->record($course, '2026-03-02', [$s->id => ['status' => 'absent']]);
        $this->assertCount(1, Notifier::$log, 'No reenvía el mismo aviso');
        $this->assertSame(1, \Aero\School\Models\Attendance::count());
    }

    public function testAttendanceIgnoresStudentsOfAnotherCourse(): void
    {
        $course = $this->course(1);
        $other = $this->student($this->course(2, 'Otro'), 'Beto');

        $r = app(AttendanceService::class)->record($course, '2026-03-02', [$other->id => ['status' => 'absent']]);
        $this->assertSame(0, $r['saved']);
    }

    public function testGradesTotalAndAnnualAverage(): void
    {
        $course = $this->course(1);
        $s = $this->student($course);
        Curriculum::seedCourse($course);
        $subject = $course->subjects()->first();
        $book = app(GradeBook::class);

        $book->save($course, $subject, 1, [$s->id => ['ser' => 9, 'saber' => 30, 'hacer' => 30, 'decidir' => 9, 'auto_ser' => 4, 'auto_decidir' => 4]]);
        $book->save($course, $subject, 2, [$s->id => ['ser' => 5, 'saber' => 20, 'hacer' => 20, 'decidir' => 5, 'auto_ser' => 2, 'auto_decidir' => 2]]);

        $row = $book->report($s)['rows'][0];
        $this->assertSame(86, (int) $row['notes'][1]);
        $this->assertSame(54, (int) $row['notes'][2]);
        $this->assertSame(70, $row['annual']);
        $this->assertTrue($row['approved']);
    }

    public function testGradeAboveMaxIsRejected(): void
    {
        $course = $this->course(1);
        $s = $this->student($course);
        Curriculum::seedCourse($course);

        $this->expectException(SchoolException::class);
        app(GradeBook::class)->save($course, $course->subjects()->first(), 1, [$s->id => ['ser' => 11]]);
    }

    public function testCannotGradeSubjectOfAnotherTenant(): void
    {
        $course = $this->course(1);
        $foreign = Subject::create(['tenant_id' => 2, 'course_id' => $this->course(2, 'X')->id, 'name' => 'Matemática']);

        $this->expectException(SchoolException::class);
        app(GradeBook::class)->save($course, $foreign, 1, []);
    }

    public function testReportCardRendersHtml(): void
    {
        $course = $this->course(1);
        $s = $this->student($course);
        Curriculum::seedCourse($course);
        Settings::create(['tenant_id' => 1, 'school_name' => 'Colegio Prueba', 'sie_code' => '80980001']);

        $html = (new ReportCard())->html($s);
        $this->assertStringContainsString('BOLETÍN DE CALIFICACIONES', $html);
        $this->assertStringContainsString('Colegio Prueba', $html);
        $this->assertStringContainsString('Matemática', $html);

        if (ReportCard::available()) {
            $this->assertStringStartsWith('%PDF', (new ReportCard())->pdf($s));
        }
    }

    public function testRemindersGroupedAndIdempotent(): void
    {
        $course = $this->course(1);
        $s = $this->student($course);
        $plan = $this->plan($course, 2);
        app(Billing::class)->generate($plan);
        Charge::where('student_id', $s->id)->update(['due_on' => today()->subDays(5)->toDateString()]);
        $settings = Settings::create(['tenant_id' => 1, 'reminders_enabled' => true, 'overdue_every_days' => 7, 'overdue_max' => 2]);

        $this->assertSame(1, app(Collections::class)->runFor($settings), 'Un solo mensaje con ambas cuotas');
        $this->assertSame(0, app(Collections::class)->runFor($settings), 'No repite antes de 7 días');
        $this->assertStringContainsString('Total vencido: 200.00', Notifier::$log[0]['body']);
    }

    public function testReportsAreScopedToTenant(): void
    {
        $a = $this->student($this->course(1));
        $b = $this->student($this->course(2, 'B'), 'Luis');
        app(Billing::class)->generate($this->plan($a->course, 1));
        app(Billing::class)->generate($this->plan($b->course, 1));
        Charge::query()->update(['due_on' => today()->subDays(40)->toDateString()]);

        $rows = (new Reports(1))->delinquency();
        $this->assertCount(1, $rows);
        $this->assertSame($a->id, $rows[0]['student_id']);
        $this->assertSame(100.0, (new Reports(1))->aging()['31-60']);
    }
}
