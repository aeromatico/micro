<?php namespace Aero\Church\Classes;

use Aero\Church\Models\Attendance;
use Aero\Church\Models\Branch;
use Aero\Church\Models\ChurchEvent;
use Aero\Church\Models\Contribution;
use Aero\Church\Models\EventRegistration;
use Aero\Church\Models\Group;
use Aero\Church\Models\GroupMember;
use Aero\Church\Models\Meeting;
use Aero\Church\Models\Member;
use Aero\Church\Models\Visit;
use Carbon\Carbon;

/**
 * Datos de los reportes y del panel. SIEMPRE acotado a una iglesia: con $tenantId explícito (servicios,
 * API, pruebas) o con lo que el usuario del panel puede ver. Cada reporte devuelve
 * ['title', 'columns', 'rows', 'summary'] para que la misma salida sirva a pantalla, Excel y PDF.
 */
class ReportService
{
    public function __construct(protected ?int $tenantId = null)
    {
    }

    protected function q(string $class)
    {
        return $this->tenantId ? $class::forTenant($this->tenantId) : $class::visible();
    }

    protected function range(?string $from, ?string $to): array
    {
        $f = $from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? Carbon::parse($from) : now()->startOfMonth();
        $t = $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? Carbon::parse($to) : now();

        return $f->greaterThan($t) ? [$t->copy()->startOfDay(), $f->copy()->endOfDay()] : [$f->copy()->startOfDay(), $t->copy()->endOfDay()];
    }

    /** Mismo largo, inmediatamente antes. */
    protected function previous(Carbon $from, Carbon $to): array
    {
        $days = max(1, (int) $from->diffInDays($to, true) + 1);

        return [$from->copy()->subDays($days), $from->copy()->subSecond()];
    }

    public static function delta(float|int $now, float|int $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100, 1) : null;
    }

    // ───────────────────────── Miembros ─────────────────────────

    public function members(array $f = []): array
    {
        $q = $this->q(Member::class)->with(['branch', 'family'])->orderBy('first_name')->orderBy('last_name');
        if (!empty($f['status']) && isset(Member::STATUSES[$f['status']])) {
            $q->where('status', $f['status']);
        }
        if (!empty($f['branch'])) {
            $q->where('branch_id', (int) $f['branch']);
        }

        $rows = [];
        foreach ($q->limit(20000)->get() as $m) {
            // Protección de menores: sin teléfono ni correo en los reportes.
            $minor = $m->is_minor;
            $rows[] = [
                $m->full_name, Member::STATUSES[$m->status] ?? $m->status, $m->branch?->name, $m->family?->name,
                $minor ? 'Menor' : $m->phone, $minor ? 'Menor' : $m->email,
                $m->birth_date?->format('d/m/Y'), $m->baptized ? 'Sí' : 'No', $m->joined_at?->format('d/m/Y'),
            ];
        }

        $counts = $this->q(Member::class)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();

        return [
            'title' => 'Directorio de miembros',
            'columns' => ['Nombre', 'Estado', 'Sede', 'Familia', 'Teléfono', 'Correo', 'Nacimiento', 'Bautizado', 'Miembro desde'],
            'rows' => $rows,
            'summary' => ['Total' => count($rows)] + collect(Member::STATUSES)->mapWithKeys(fn ($l, $k) => [$l => (int) ($counts[$k] ?? 0)])->all(),
        ];
    }

    // ───────────────────────── Crecimiento ─────────────────────────

    /** Altas por mes de los últimos 12 meses y total acumulado. */
    public function growth(): array
    {
        $start = now()->startOfMonth()->subMonths(11);
        $byMonth = $this->q(Member::class)->where('created_at', '>=', $start)->get(['created_at'])
            ->groupBy(fn ($m) => $m->created_at->format('Y-m'))->map->count();
        $before = $this->q(Member::class)->where('created_at', '<', $start)->count();

        $rows = [];
        $acc = $before;
        for ($i = 11; $i >= 0; $i--) {
            $key = now()->startOfMonth()->subMonths($i)->format('Y-m');
            $n = (int) ($byMonth[$key] ?? 0);
            $acc += $n;
            $rows[] = [$key, $n, $acc];
        }

        return [
            'title' => 'Crecimiento de la congregación',
            'columns' => ['Mes', 'Altas', 'Acumulado'],
            'rows' => $rows,
            'summary' => ['Altas en 12 meses' => array_sum(array_column($rows, 1)), 'Total hoy' => $acc],
        ];
    }

    // ───────────────────────── Asistencia ─────────────────────────

    /** @return array{events: int, meetings: int, total: int} asistencia confirmada en el rango. */
    public function attendanceTotals(Carbon $from, Carbon $to): array
    {
        $events = $this->q(EventRegistration::class)->where('status', 'registered')->whereBetween('checked_in_at', [$from, $to])->count();
        $meetings = $this->q(Attendance::class)->where('present', true)
            ->whereIn('meeting_id', $this->q(Meeting::class)->whereDate('meeting_date', '>=', $from->toDateString())->whereDate('meeting_date', '<=', $to->toDateString())->select('id'))->count();

        return ['events' => $events, 'meetings' => $meetings, 'total' => $events + $meetings];
    }

    public function attendance(?string $from = null, ?string $to = null): array
    {
        [$f, $t] = $this->range($from, $to);
        [$pf, $pt] = $this->previous($f, $t);

        $rows = [];
        $events = $this->q(ChurchEvent::class)->where('status', 'scheduled')->whereBetween('starts_at', [$f, $t])->orderBy('starts_at')->get();
        foreach ($events as $e) {
            $reg = $e->registrations()->where('status', 'registered');
            $rows[] = ['Evento', $e->title, $e->starts_at->format('d/m/Y H:i'), (clone $reg)->count(), (clone $reg)->whereNotNull('checked_in_at')->count()];
        }
        $meetings = $this->q(Meeting::class)->with('group')->whereDate('meeting_date', '>=', $f->toDateString())->whereDate('meeting_date', '<=', $t->toDateString())->orderBy('meeting_date')->get();
        foreach ($meetings as $m) {
            $att = $m->attendances();
            $rows[] = ['Reunión de grupo', ($m->group?->name ?? '—') . ($m->title ? ' · ' . $m->title : ''), $m->meeting_date->format('d/m/Y'), (clone $att)->count(), (clone $att)->where('present', true)->count()];
        }

        $now = $this->attendanceTotals($f, $t);
        $prev = $this->attendanceTotals($pf, $pt);

        return [
            'title' => 'Asistencia',
            'columns' => ['Tipo', 'Actividad', 'Fecha', 'Esperados', 'Asistieron'],
            'rows' => $rows,
            'summary' => [
                'Asistencias (periodo)' => $now['total'], 'Periodo anterior' => $prev['total'],
                'Variación' => ($d = self::delta($now['total'], $prev['total'])) === null ? 'sin base de comparación' : ($d > 0 ? '+' : '') . $d . '%',
                'Periodo' => $f->format('d/m/Y') . ' – ' . $t->format('d/m/Y'),
                'Comparado con' => $pf->format('d/m/Y') . ' – ' . $pt->format('d/m/Y'),
            ],
        ];
    }

    // ───────────────────────── Ministerios y grupos ─────────────────────────

    public function groups(?string $from = null, ?string $to = null): array
    {
        [$f, $t] = $this->range($from, $to);

        $rows = [];
        foreach ($this->q(Group::class)->with('branch')->orderBy('name')->get() as $g) {
            $members = GroupMember::where('group_id', $g->id)->whereNull('left_at')->count();
            $joined = GroupMember::where('group_id', $g->id)->whereDate('joined_at', '>=', $f->toDateString())->whereDate('joined_at', '<=', $t->toDateString())->count();
            $meetingIds = Meeting::where('group_id', $g->id)->whereDate('meeting_date', '>=', $f->toDateString())->whereDate('meeting_date', '<=', $t->toDateString())->pluck('id');
            $present = Attendance::whereIn('meeting_id', $meetingIds)->where('present', true)->count();
            $marked = Attendance::whereIn('meeting_id', $meetingIds)->count();
            $avg = $meetingIds->count() ? round($present / $meetingIds->count(), 1) : 0;
            $rows[] = [
                $g->name, Group::TYPES[$g->type] ?? $g->type, $g->branch?->name, $g->is_active ? 'Activo' : 'Inactivo', $members, $joined,
                $meetingIds->count(), $avg, $marked ? round($present / $marked * 100, 1) . '%' : '—',
            ];
        }

        return [
            'title' => 'Participación por ministerio y grupo',
            'columns' => ['Grupo', 'Tipo', 'Sede', 'Estado', 'Integrantes', 'Nuevos en el periodo', 'Reuniones', 'Asistencia promedio', '% de asistencia'],
            'rows' => $rows,
            'summary' => ['Grupos' => count($rows), 'Periodo' => $f->format('d/m/Y') . ' – ' . $t->format('d/m/Y')],
        ];
    }

    // ───────────────────────── Visitantes ─────────────────────────

    public function visitors(?string $from = null, ?string $to = null): array
    {
        [$f, $t] = $this->range($from, $to);
        $q = $this->q(Visit::class)->whereDate('visit_date', '>=', $f->toDateString())->whereDate('visit_date', '<=', $t->toDateString());

        $by = (clone $q)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $bySource = (clone $q)->selectRaw('source, count(*) as n')->groupBy('source')->pluck('n', 'source')->all();
        $total = array_sum($by);
        $integrated = (int) ($by['integrated'] ?? 0);

        $rows = [];
        foreach (Visit::STATUSES as $k => $label) {
            $n = (int) ($by[$k] ?? 0);
            $rows[] = [$label, $n, $total ? round($n / $total * 100, 1) . '%' : '—'];
        }

        return [
            'title' => 'Seguimiento de visitantes',
            'columns' => ['Estado', 'Visitantes', '% del total'],
            'rows' => $rows,
            'summary' => [
                'Visitantes' => $total, 'Integrados' => $integrated,
                'Tasa de integración' => $total ? round($integrated / $total * 100, 1) . '%' : '—',
                'Por formulario QR' => (int) ($bySource['qr'] ?? 0), 'Registrados a mano' => (int) ($bySource['manual'] ?? 0),
                'Periodo' => $f->format('d/m/Y') . ' – ' . $t->format('d/m/Y'),
            ],
        ];
    }

    // ───────────────────────── Aportes ─────────────────────────

    /** Resumen agregado (sin nombres): apto para el pastor. */
    public function givingSummary(?string $from = null, ?string $to = null, ?int $branchId = null): array
    {
        [$f, $t] = $this->range($from, $to);
        $stats = new GivingStats($f->toDateString(), $t->toDateString(), $branchId, $this->tenantId);

        $rows = [];
        foreach ($stats->byFund() as $r) {
            $rows[] = [$r->label, $r->currency, round((float) $r->total, 2)];
        }
        $totals = [];
        foreach ($stats->totals() as $cur => $x) {
            $totals["Total {$cur}"] = number_format($x['total'], 2, ',', '.') . " ({$x['count']} aportes)";
        }

        return ['title' => 'Resumen de aportes', 'columns' => ['Concepto', 'Moneda', 'Total'], 'rows' => $rows, 'summary' => $totals + ['Periodo' => $f->format('d/m/Y') . ' – ' . $t->format('d/m/Y')]];
    }

    /** Detalle con aportantes: solo tesorería y administración. */
    public function contributions(?string $from = null, ?string $to = null): array
    {
        [$f, $t] = $this->range($from, $to);
        $rows = [];
        foreach ($this->q(Contribution::class)->with(['fund', 'member', 'branch'])->confirmed()->whereDate('date', '>=', $f->toDateString())->whereDate('date', '<=', $t->toDateString())->orderBy('date')->limit(20000)->get() as $c) {
            $rows[] = [
                $c->date->format('d/m/Y'), $c->fund?->name, $c->donor_label, $c->branch?->name,
                Contribution::METHODS[$c->method] ?? $c->method, $c->currency, round((float) $c->amount, 2), $c->receipt_number,
            ];
        }

        return [
            'title' => 'Aportes confirmados (detalle)',
            'columns' => ['Fecha', 'Concepto', 'Aportante', 'Sede', 'Forma de pago', 'Moneda', 'Monto', 'Comprobante'],
            'rows' => $rows,
            'summary' => ['Aportes' => count($rows), 'Periodo' => $f->format('d/m/Y') . ' – ' . $t->format('d/m/Y')],
        ];
    }

    // ───────────────────────── Panel ─────────────────────────

    public function dashboard(bool $withGiving, bool $withPastoral): array
    {
        $monthStart = now()->startOfMonth();
        $prevStart = $monthStart->copy()->subMonth();
        $prevEnd = $monthStart->copy()->subSecond();

        $newNow = $this->q(Member::class)->where('created_at', '>=', $monthStart)->count();
        $newPrev = $this->q(Member::class)->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $visNow = $this->q(Visit::class)->where('visit_date', '>=', $monthStart->toDateString())->count();
        $visInt = $this->q(Visit::class)->where('visit_date', '>=', $monthStart->toDateString())->where('status', 'integrated')->count();

        $attNow = $this->attendanceTotals(now()->subDays(27)->startOfDay(), now()->endOfDay());
        $attPrev = $this->attendanceTotals(now()->subDays(55)->startOfDay(), now()->subDays(28)->endOfDay());

        $out = [
            'active_members' => $this->q(Member::class)->where('status', 'active')->count(),
            'total_members'  => $this->q(Member::class)->count(),
            'new_members'    => ['now' => $newNow, 'prev' => $newPrev, 'delta' => self::delta($newNow, $newPrev)],
            'visitors'       => ['now' => $visNow, 'integrated' => $visInt, 'rate' => $visNow ? round($visInt / $visNow * 100, 1) : 0.0],
            'attendance'     => ['now' => $attNow['total'], 'prev' => $attPrev['total'], 'delta' => self::delta($attNow['total'], $attPrev['total'])],
            'upcoming_events' => $this->q(ChurchEvent::class)->where('status', 'scheduled')->whereBetween('starts_at', [now(), now()->addDays(14)])->count(),
            'growth'         => $this->growth()['rows'],
            'weekly'         => $this->weeklyAttendance(8),
            'birthdays'      => $this->birthdays(7),
        ];

        if ($withGiving) {
            $now = new GivingStats($monthStart->toDateString(), now()->toDateString(), null, $this->tenantId);
            $prev = new GivingStats($prevStart->toDateString(), $prevEnd->toDateString(), null, $this->tenantId);
            $out['giving'] = ['now' => $now->totals(), 'prev' => $prev->totals()];
        }
        if ($withPastoral) {
            $out['prayers_open'] = $this->q(\Aero\Church\Models\Prayer::class)->whereIn('status', ['open', 'in_prayer'])->count();
            $out['visits_pending'] = $this->q(Visit::class)->needsAttention()->count();
        }

        return $out;
    }

    /** Asistencias confirmadas por semana (eventos + reuniones), de la más antigua a la actual. */
    public function weeklyAttendance(int $weeks = 8): array
    {
        $start = now()->startOfWeek()->subWeeks($weeks - 1);
        $ev = $this->q(EventRegistration::class)->where('status', 'registered')->where('checked_in_at', '>=', $start)->get(['checked_in_at'])
            ->groupBy(fn ($r) => $r->checked_in_at->copy()->startOfWeek()->toDateString())->map->count();
        $mt = $this->q(Attendance::class)->where('present', true)->whereHas('meeting', fn ($q) => $q->where('meeting_date', '>=', $start->toDateString()))->with('meeting:id,meeting_date')->get()
            ->groupBy(fn ($a) => $a->meeting->meeting_date->copy()->startOfWeek()->toDateString())->map->count();

        $out = [];
        for ($i = 0; $i < $weeks; $i++) {
            $w = $start->copy()->addWeeks($i)->toDateString();
            $out[$w] = (int) ($ev[$w] ?? 0) + (int) ($mt[$w] ?? 0);
        }

        return $out;
    }

    /** Cumpleaños de adultos en los próximos N días: nombre y fecha (sin teléfono ni datos de contacto). */
    public function birthdays(int $days = 7): array
    {
        $months = collect(range(0, $days))->map(fn ($d) => now()->addDays($d)->month)->unique()->all();
        $rows = [];
        foreach ($this->q(Member::class)->whereIn('status', ['active', 'new'])->whereNotNull('birth_date')->where(function ($w) use ($months) {
            foreach ($months as $mo) {
                $w->orWhereMonth('birth_date', $mo);
            }
        })->get() as $m) {
            if ($m->is_minor) {
                continue;
            }
            $next = $m->birth_date->copy()->year(now()->year);
            if ($next->lt(now()->startOfDay())) {
                $next->addYear();
            }
            if ($next->lte(now()->addDays($days)->endOfDay())) {
                $rows[] = ['name' => $m->first_name . ($m->last_name ? ' ' . mb_substr($m->last_name, 0, 1) . '.' : ''), 'date' => $next->format('d/m'), 'sort' => $next->timestamp];
            }
        }
        usort($rows, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_map(fn ($r) => ['name' => $r['name'], 'date' => $r['date']], $rows);
    }
}
