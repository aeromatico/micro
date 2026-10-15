<?php namespace Aero\School\Classes;

use Aero\School\Models\Charge;
use Aero\School\Models\Course;
use Aero\School\Models\Payment;
use Illuminate\Support\Facades\DB;

/** Reportes de cobranza. Todo se calcula por tenant (nunca entre colegios). */
class Reports
{
    public function __construct(protected int $tenantId)
    {
    }

    /** Morosidad por alumno: deuda vencida, cuotas vencidas y antigüedad. */
    public function delinquency(?int $courseId = null): array
    {
        $rows = Charge::query()->from('aero_school_charges as ch')
            ->join('aero_school_students as s', 's.id', '=', 'ch.student_id')
            ->leftJoin('aero_school_courses as c', 'c.id', '=', 's.course_id')
            ->where('ch.tenant_id', $this->tenantId)->whereIn('ch.status', ['pending', 'partial'])
            ->whereDate('ch.due_on', '<', today())
            ->when($courseId, fn ($q) => $q->where('s.course_id', $courseId))
            ->groupBy('s.id', 's.first_name', 's.paternal_surname', 's.maternal_surname', 'c.name')
            ->selectRaw('s.id, s.first_name, s.paternal_surname, s.maternal_surname, c.name as course,
                COUNT(*) as charges, SUM(ch.amount - ch.discount - ch.paid_amount) as due, MIN(ch.due_on) as oldest')
            ->orderByDesc('due')->get();

        return $rows->map(fn ($r) => [
            'student_id' => $r->id,
            'student'    => trim("{$r->paternal_surname} {$r->maternal_surname} {$r->first_name}"),
            'course'     => $r->course,
            'charges'    => (int) $r->charges,
            'due'        => round((float) $r->due, 2),
            'days'       => (int) \Carbon\Carbon::parse($r->oldest)->diffInDays(today(), true),
        ])->all();
    }

    /** Antigüedad de la deuda vencida en tramos. */
    public function aging(): array
    {
        $buckets = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];
        Charge::where('tenant_id', $this->tenantId)->overdue()->get()->each(function (Charge $c) use (&$buckets) {
            $d = (int) $c->due_on->diffInDays(today(), true);
            $k = $d <= 30 ? '0-30' : ($d <= 60 ? '31-60' : ($d <= 90 ? '61-90' : '90+'));
            $buckets[$k] += $c->due;
        });

        return array_map(fn ($v) => round($v, 2), $buckets);
    }

    /** Facturado, cobrado y por cobrar por curso. */
    public function byCourse(?int $yearId = null): array
    {
        return Charge::query()->from('aero_school_charges as ch')
            ->join('aero_school_students as s', 's.id', '=', 'ch.student_id')
            ->leftJoin('aero_school_courses as c', 'c.id', '=', 's.course_id')
            ->where('ch.tenant_id', $this->tenantId)->where('ch.status', '!=', 'void')
            ->when($yearId, fn ($q) => $q->where('ch.year_id', $yearId))
            ->groupBy('c.id', 'c.name')
            ->selectRaw('c.name as course, COUNT(DISTINCT s.id) as students, SUM(ch.amount - ch.discount) as billed,
                SUM(ch.paid_amount) as paid')
            ->orderBy('c.name')->get()
            ->map(fn ($r) => [
                'course' => $r->course ?: 'Sin curso', 'students' => (int) $r->students, 'billed' => round((float) $r->billed, 2),
                'paid' => round((float) $r->paid, 2), 'pending' => round((float) $r->billed - (float) $r->paid, 2),
                'rate' => (float) $r->billed > 0 ? round((float) $r->paid * 100 / (float) $r->billed, 1) : 0,
            ])->all();
    }

    /** Ingresos recibidos por mes (pagos no anulados). */
    public function incomeByMonth(int $months = 12): array
    {
        $driver = DB::connection()->getDriverName();
        $expr = $driver === 'sqlite' ? "strftime('%Y-%m', paid_at)" : "DATE_FORMAT(paid_at, '%Y-%m')";

        return Payment::where('tenant_id', $this->tenantId)->whereNull('voided_at')
            ->where('paid_at', '>=', now()->subMonths($months - 1)->startOfMonth())
            ->selectRaw("$expr as month, SUM(amount) as total, COUNT(*) as payments")
            ->groupBy('month')->orderBy('month')->get()
            ->map(fn ($r) => ['month' => $r->month, 'total' => round((float) $r->total, 2), 'payments' => (int) $r->payments])->all();
    }

    public function kpis(): array
    {
        $open = Charge::where('tenant_id', $this->tenantId)->open();
        $overdue = (clone $open)->whereDate('due_on', '<', today());

        return [
            'students' => \Aero\School\Models\Student::where('tenant_id', $this->tenantId)->where('status', 'active')->count(),
            'pending'  => round((float) (clone $open)->sum(DB::raw('amount - discount - paid_amount')), 2),
            'overdue'  => round((float) (clone $overdue)->sum(DB::raw('amount - discount - paid_amount')), 2),
            'debtors'  => (clone $overdue)->distinct('student_id')->count('student_id'),
            'month'    => round((float) Payment::where('tenant_id', $this->tenantId)->whereNull('voided_at')
                ->where('paid_at', '>=', now()->startOfMonth())->sum('amount'), 2),
            'courses'  => Course::where('tenant_id', $this->tenantId)->where('is_active', true)->count(),
        ];
    }
}
