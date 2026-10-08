<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\AccessLog;
use Aero\Gym\Models\Booking;
use Aero\Gym\Models\Membership;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Reportes del gimnasio de un tenant (null = todos, solo superadmin). */
class Reports
{
    public function __construct(protected ?int $tenantId)
    {
    }

    protected function scope($q, string $table)
    {
        return $this->tenantId ? $q->where("{$table}.tenant_id", $this->tenantId) : $q;
    }

    /** Ingresos concedidos por día. */
    public function attendanceByDay(Carbon $from, Carbon $to): array
    {
        $rows = $this->scope(AccessLog::query(), 'aero_gym_access_logs')
            ->where('granted', true)->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as d, COUNT(*) as n')->groupBy('d')->pluck('n', 'd')->all();

        $out = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $out[$d->toDateString()] = (int) ($rows[$d->toDateString()] ?? 0);
        }

        return $out;
    }

    /** Clases más demandadas: reservas, asistencias, ocupación media. */
    public function popularClasses(Carbon $from, Carbon $to, int $limit = 10): array
    {
        $q = DB::table('aero_gym_sessions as s')
            ->join('aero_gym_class_types as c', 'c.id', '=', 's.class_type_id')
            ->leftJoin('aero_gym_bookings as b', function ($j) {
                $j->on('b.session_id', '=', 's.id')->whereIn('b.status', ['booked', 'attended', 'no_show']);
            })
            ->whereBetween('s.starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('s.status', '!=', 'cancelled');
        if ($this->tenantId) {
            $q->where('s.tenant_id', $this->tenantId);
        }

        return $q->groupBy('c.id', 'c.name')
            ->selectRaw("c.name, COUNT(DISTINCT s.id) as sesiones, COUNT(b.id) as reservas,
                SUM(CASE WHEN b.status = 'attended' THEN 1 ELSE 0 END) as asistencias")
            ->orderByDesc('reservas')->limit($limit)->get()
            ->map(fn ($r) => [
                'name' => $r->name, 'sessions' => (int) $r->sesiones, 'bookings' => (int) $r->reservas,
                'attended' => (int) $r->asistencias,
                'avg_per_session' => $r->sesiones ? round($r->reservas / $r->sesiones, 1) : 0,
            ])->all();
    }

    /** Ocupación por clase en el rango: reservas / cupos de sesiones. */
    public function occupancy(Carbon $from, Carbon $to): float
    {
        $q = DB::table('aero_gym_sessions')->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->where('status', '!=', 'cancelled');
        if ($this->tenantId) {
            $q->where('tenant_id', $this->tenantId);
        }
        $cap = (int) $q->sum('capacity');
        if (!$cap) {
            return 0.0;
        }
        $b = $this->scope(Booking::query(), 'aero_gym_bookings')->whereIn('status', ['booked', 'attended', 'no_show'])
            ->whereHas('session', fn ($s) => $s->whereBetween('starts_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->where('status', '!=', 'cancelled'))
            ->count();

        return round($b / $cap * 100, 1);
    }

    /**
     * Renovaciones vs bajas por mes. Renovación: membresía con renewed_from o
     * cuyo socio ya tuvo otra anterior; baja: membresía que venció sin que
     * haya una posterior que empiece dentro de 30 días.
     */
    public function renewalsVsChurn(Carbon $from, Carbon $to): array
    {
        $out = [];
        for ($m = $from->copy()->startOfMonth(); $m->lte($to); $m->addMonth()) {
            $out[$m->format('Y-m')] = ['new' => 0, 'renewed' => 0, 'churned' => 0];
        }

        $paid = $this->scope(Membership::query(), 'aero_gym_memberships')->whereIn('status', ['active', 'expired']);

        (clone $paid)->whereBetween('starts_on', [$from->copy()->startOfMonth(), $to->copy()->endOfMonth()])->get()
            ->each(function ($m) use (&$out) {
                $key = $m->starts_on->format('Y-m');
                if (!isset($out[$key])) {
                    return;
                }
                $previous = Membership::where('member_id', $m->member_id)->where('id', '<', $m->id)
                    ->whereIn('status', ['active', 'expired'])->exists();
                $out[$key][$previous || $m->renewed_from_id ? 'renewed' : 'new']++;
            });

        (clone $paid)->where('status', 'expired')->whereBetween('ends_on', [$from->copy()->startOfMonth(), $to->copy()->endOfMonth()])->get()
            ->each(function ($m) use (&$out) {
                $key = $m->ends_on->format('Y-m');
                $continued = Membership::where('member_id', $m->member_id)->where('id', '!=', $m->id)
                    ->whereIn('status', ['active', 'expired'])
                    ->whereDate('starts_on', '<=', $m->ends_on->copy()->addDays(30))
                    ->whereDate('ends_on', '>', $m->ends_on)->exists();
                if (isset($out[$key]) && !$continued) {
                    $out[$key]['churned']++;
                }
            });

        return $out;
    }

    public function summary(): array
    {
        $members = $this->scope(\Aero\Gym\Models\Member::query(), 'aero_gym_members');
        $ms = $this->scope(Membership::query(), 'aero_gym_memberships');

        return [
            'members'          => (clone $members)->count(),
            'active'           => (clone $ms)->where('status', 'active')->whereDate('ends_on', '>=', today())->distinct('member_id')->count('member_id'),
            'expiring_7'       => (clone $ms)->where('status', 'active')->whereBetween('ends_on', [today(), today()->addDays(7)])->count(),
            'pending_payment'  => (clone $ms)->where('status', 'pending')->count(),
        ];
    }
}
