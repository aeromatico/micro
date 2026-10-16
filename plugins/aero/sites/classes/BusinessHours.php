<?php namespace Aero\Sites\Classes;

use Carbon\Carbon;

/**
 * Horario de atención de un negocio: lista [{day, open, close, closed}].
 * Un día puede repetirse (turno partido) y un cierre menor o igual a la
 * apertura se entiende pasada la medianoche. Mismo formato que el horario de
 * Aero.Shop, para poder sincronizarlos sin conversión.
 */
class BusinessHours
{
    public const DAYS = [
        'mon' => 'Lunes', 'tue' => 'Martes', 'wed' => 'Miércoles', 'thu' => 'Jueves',
        'fri' => 'Viernes', 'sat' => 'Sábado', 'sun' => 'Domingo',
    ];

    public const SHORT = [
        'mon' => 'Lun', 'tue' => 'Mar', 'wed' => 'Mié', 'thu' => 'Jue',
        'fri' => 'Vie', 'sat' => 'Sáb', 'sun' => 'Dom',
    ];

    /** Turnos por día que ofrece el formulario. */
    public const MAX_SHIFTS = 2;

    /**
     * Convierte lo que envía el formulario (days[mon][closed], days[mon][shifts][0][open]…)
     * en la lista guardada. Devuelve [lista, errores].
     *
     * @return array{0: array<int, array>, 1: array<int, string>}
     */
    public static function fromForm(array $days): array
    {
        $list = [];
        $errors = [];

        foreach (self::DAYS as $key => $label) {
            $row = (array) ($days[$key] ?? []);

            if (!empty($row['closed'])) {
                $list[] = ['day' => $key, 'open' => null, 'close' => null, 'closed' => true];
                continue;
            }

            $count = 0;
            foreach (array_slice((array) ($row['shifts'] ?? []), 0, self::MAX_SHIFTS) as $shift) {
                $open = self::time($shift['open'] ?? '');
                $close = self::time($shift['close'] ?? '');

                if ($open === null && $close === null) {
                    continue;
                }
                if ($open === null || $close === null) {
                    $errors[] = "{$label}: completa la hora de apertura y la de cierre.";
                    continue;
                }
                if ($open === $close) {
                    $errors[] = "{$label}: la apertura y el cierre no pueden ser iguales.";
                    continue;
                }

                $list[] = ['day' => $key, 'open' => $open, 'close' => $close, 'closed' => false];
                $count++;
            }

            if ($count === 0 && !$errors) {
                // Sin turnos cargados: se guarda como cerrado en vez de dejar un día «vacío».
                $list[] = ['day' => $key, 'open' => null, 'close' => null, 'closed' => true];
            }
        }

        return [$list, $errors];
    }

    /** 'HH:MM' válido (acepta 'H:MM' y 'HH:MM:SS') o null. */
    public static function time($value): ?string
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', trim((string) $value), $m)) {
            return null;
        }

        return ((int) $m[1] <= 23 && (int) $m[2] <= 59) ? sprintf('%02d:%02d', $m[1], $m[2]) : null;
    }

    /** Turnos por día: ['mon' => [['08:00','19:30']], 'sat' => [], ...] (vacío = cerrado). */
    public static function byDay(array $list): array
    {
        $days = array_fill_keys(array_keys(self::DAYS), []);

        foreach ($list as $h) {
            $day = $h['day'] ?? '';
            if (!isset($days[$day]) || !empty($h['closed']) || empty($h['open']) || empty($h['close'])) {
                continue;
            }
            $days[$day][] = [$h['open'], $h['close']];
        }

        return array_map(function ($shifts) {
            usort($shifts, fn ($a, $b) => strcmp($a[0], $b[0]));
            return $shifts;
        }, $days);
    }

    /** ¿Hay al menos un turno abierto en la semana? */
    public static function hasAny(array $list): bool
    {
        return (bool) array_filter(self::byDay($list));
    }

    /** '08:00' → '8:00'. */
    public static function pretty(string $time): string
    {
        return $time !== '' && $time[0] === '0' ? substr($time, 1) : $time;
    }

    /** '8:00 – 19:30' o '8:00 – 12:00 y 14:00 – 19:00'; 'Cerrado' si no hay turnos. */
    public static function shiftsText(array $shifts): string
    {
        if (!$shifts) {
            return 'Cerrado';
        }

        return implode(' y ', array_map(fn ($s) => self::pretty($s[0]) . ' – ' . self::pretty($s[1]), $shifts));
    }

    /**
     * Días consecutivos con el mismo horario se juntan: «Lun a Mar», «Mié».
     *
     * @return array<int, array{label: string, value: string, closed: bool}>
     */
    public static function grouped(array $list): array
    {
        if (!self::hasAny($list)) {
            return [];
        }

        $groups = [];
        foreach (self::byDay($list) as $day => $shifts) {
            $text = self::shiftsText($shifts);
            $last = count($groups) - 1;

            if ($last >= 0 && $groups[$last]['value'] === $text) {
                $groups[$last]['to'] = $day;
            } else {
                $groups[] = ['from' => $day, 'to' => $day, 'value' => $text, 'closed' => !$shifts];
            }
        }

        return array_map(function ($g) {
            $label = $g['from'] === $g['to']
                ? self::DAYS[$g['from']]
                : self::SHORT[$g['from']] . ' a ' . self::SHORT[$g['to']];

            return ['label' => $label, 'value' => $g['value'], 'closed' => $g['closed']];
        }, $groups);
    }

    /** ¿Está abierto en este momento (hora local del negocio)? */
    public static function isOpenNow(array $list, string $timezone = 'America/La_Paz', ?Carbon $at = null): bool
    {
        $now = ($at ? $at->copy() : Carbon::now())->setTimezone($timezone);
        $days = array_keys(self::DAYS);
        $today = $days[$now->dayOfWeekIso - 1];
        $yesterday = $days[($now->dayOfWeekIso + 5) % 7];
        $time = $now->format('H:i');
        $byDay = self::byDay($list);

        foreach ($byDay[$today] as [$open, $close]) {
            $overnight = $close <= $open;
            if ($time >= $open && ($overnight || $time < $close)) {
                return true;
            }
        }
        foreach ($byDay[$yesterday] as [$open, $close]) {
            if ($close <= $open && $time < $close) {
                return true;
            }
        }

        return false;
    }
}
