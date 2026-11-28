<?php namespace Aero\Church\Classes;

use Aero\Church\Models\ServiceTime;
use Carbon\Carbon;

/** Horario semanal de UNA iglesia listo para mostrar: agrupado por día y con la próxima reunión. */
class WeeklySchedule
{
    /** Hora «de pared» de la iglesia: la aplicación guarda en UTC, el horario se escribe en hora local. */
    public static function now(): Carbon
    {
        return Carbon::now(LocalTime::zone());
    }

    /** @return array días con reuniones, de lunes a domingo; vacío si no hay ninguna activa */
    public static function days(?int $tenantId, ?Carbon $now = null): array
    {
        $now = $now ?: self::now();
        $rows = self::rows($tenantId);
        $manyBranches = $rows->pluck('branch_id')->unique()->count() > 1;
        $days = [];

        foreach ($rows as $r) {
            $day = (int) $r->day;
            $days[$day] ??= ['day' => $day, 'label' => ServiceTime::DAYS[$day], 'today' => $day === $now->isoWeekday(), 'items' => []];
            $days[$day]['items'][] = self::present($r, $manyBranches);
        }

        return array_values($days);
    }

    /**
     * La reunión que está en curso o la siguiente.
     * @return array|null con `date` (Carbon de inicio), `when` («Hoy», «Mañana», «Domingo») y `now` (en curso)
     */
    public static function next(?int $tenantId, ?Carbon $now = null): ?array
    {
        $now = $now ?: self::now();
        $rows = self::rows($tenantId);
        $manyBranches = $rows->pluck('branch_id')->unique()->count() > 1;
        $best = null;

        foreach ($rows as $r) {
            $ahead = ((int) $r->day - $now->isoWeekday() + 7) % 7;
            $start = $now->copy()->startOfDay()->addDays($ahead)->setTimeFromTimeString($r->starts_at);
            $end = $r->ends_at ? $start->copy()->setTimeFromTimeString($r->ends_at) : $start->copy()->addHour();
            if ($end->lte($now)) {          // la de hoy ya terminó: la próxima es en una semana
                $start->addWeek();
                $end->addWeek();
                $ahead += 7;
            }
            if ($best === null || $start->lt($best['date'])) {
                $best = self::present($r, $manyBranches) + [
                    'date' => $start,
                    'now'  => $start->lte($now),
                    'when' => $ahead === 0 ? 'Hoy' : ($ahead === 1 ? 'Mañana' : ServiceTime::DAYS[(int) $r->day]),
                ];
            }
        }

        return $best;
    }

    public static function exists(?int $tenantId): bool
    {
        return $tenantId && ServiceTime::forTenant($tenantId)->active()->exists();
    }

    /** «3:45 – 6:00 p. m.», «11:00 a. m. – 12:30 p. m.» */
    public static function range(string $start, ?string $end): string
    {
        $part = function (string $t) {
            [$h, $m] = array_map('intval', explode(':', $t));

            return [($h % 12 ?: 12) . ':' . sprintf('%02d', $m), $h < 12 ? 'a. m.' : 'p. m.'];
        };
        [$a, $ap] = $part($start);
        if (!$end) {
            return "$a $ap";
        }
        [$b, $bp] = $part($end);

        return $ap === $bp ? "$a – $b $bp" : "$a $ap – $b $bp";
    }

    protected static function rows(?int $tenantId)
    {
        return ServiceTime::forTenant($tenantId)->active()->with('branch')->orderBy('week_position')->orderBy('id')->get();
    }

    protected static function present(ServiceTime $r, bool $withBranch): array
    {
        return [
            'title'  => $r->title,
            'time'   => self::range($r->starts_at, $r->ends_at),
            'place'  => implode(' · ', array_filter([$withBranch ? $r->branch?->name : null, $r->location])),
            'note'   => $r->note,
        ];
    }
}
