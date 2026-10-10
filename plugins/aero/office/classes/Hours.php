<?php namespace Aero\Office\Classes;

use Carbon\Carbon;

/**
 * Horarios semanales guardados como lista JSON de filas
 * {weekday: 1..7 (ISO, lunes=1), start_time: "HH:MM", end_time: "HH:MM"}.
 * Varias filas el mismo día = turnos partidos (el hueco entre ellas es el descanso).
 */
class Hours
{
    public static function weekdays(): array
    {
        return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    }

    /** Intervalos [Carbon, Carbon] de un día según las filas; vacío si no hay. */
    public static function intervalsFor(?array $rows, Carbon $day): array
    {
        $out = [];
        foreach ((array) $rows as $r) {
            if ((int) ($r['weekday'] ?? 0) !== (int) $day->dayOfWeekIso) {
                continue;
            }
            $start = self::at($day, $r['start_time'] ?? null);
            $end = self::at($day, $r['end_time'] ?? null);
            if ($start && $end && $end->gt($start)) {
                $out[] = [$start, $end];
            }
        }

        return self::merge($out);
    }

    public static function at(Carbon $day, ?string $hhmm): ?Carbon
    {
        if (!$hhmm || !preg_match('/^(\d{1,2}):(\d{2})/', $hhmm, $m)) {
            return null;
        }

        return $day->copy()->setTime((int) $m[1], (int) $m[2], 0);
    }

    /** Intersección de dos listas de intervalos. */
    public static function intersect(array $a, array $b): array
    {
        $out = [];
        foreach ($a as [$s1, $e1]) {
            foreach ($b as [$s2, $e2]) {
                $s = $s1->gt($s2) ? $s1 : $s2;
                $e = $e1->lt($e2) ? $e1 : $e2;
                if ($e->gt($s)) {
                    $out[] = [$s->copy(), $e->copy()];
                }
            }
        }

        return self::merge($out);
    }

    /** Resta intervalos "ocupados" de los libres. */
    public static function subtract(array $free, array $busy): array
    {
        foreach ($busy as [$bs, $be]) {
            $next = [];
            foreach ($free as [$fs, $fe]) {
                if ($be->lte($fs) || $bs->gte($fe)) {
                    $next[] = [$fs, $fe];
                    continue;
                }
                if ($bs->gt($fs)) {
                    $next[] = [$fs, $bs->copy()];
                }
                if ($be->lt($fe)) {
                    $next[] = [$be->copy(), $fe];
                }
            }
            $free = $next;
        }

        return $free;
    }

    public static function merge(array $intervals): array
    {
        usort($intervals, fn ($x, $y) => $x[0]->timestamp <=> $y[0]->timestamp);
        $out = [];
        foreach ($intervals as [$s, $e]) {
            if ($out && $s->lte($out[count($out) - 1][1])) {
                if ($e->gt($out[count($out) - 1][1])) {
                    $out[count($out) - 1][1] = $e;
                }
                continue;
            }
            $out[] = [$s, $e];
        }

        return $out;
    }
}
