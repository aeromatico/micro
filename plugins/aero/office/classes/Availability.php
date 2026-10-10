<?php namespace Aero\Office\Classes;

use Aero\Office\Models\Booking;
use Aero\Office\Models\Branch;
use Aero\Office\Models\OfficeSettings;
use Aero\Office\Models\Service;
use Aero\Office\Models\TimeOff;
use Aero\Office\Models\Worker;
use Carbon\Carbon;

/**
 * Motor de disponibilidad: horario de la sucursal ∩ horario del profesional,
 * menos ausencias/feriados, menos reservas vigentes (con su descanso).
 * Es la ÚNICA fuente de verdad: el portal muestra lo que devuelve y
 * BookingService vuelve a validar con él dentro de la transacción.
 */
class Availability
{
    /** Profesionales que pueden atender ese servicio en esa sucursal (activos). */
    public static function eligibleWorkers(Service $service, Branch $branch, bool $publicOnly = false)
    {
        return Worker::where('tenant_id', $service->tenant_id)->active()
            ->when($publicOnly, fn ($q) => $q->publicly())
            ->whereIn('id', $service->workers()->pluck('aero_office_workers.id'))
            ->whereIn('id', $branch->workers()->pluck('aero_office_workers.id'))
            ->orderBy('name')->get();
    }

    /** Horario laboral efectivo del profesional ese día en esa sucursal. */
    public static function workingIntervals(Worker $worker, Branch $branch, Carbon $day): array
    {
        $open = Hours::intervalsFor($branch->hours, $day);
        if (!$open) {
            return [];
        }
        $own = (array) $worker->hours;

        return $own ? Hours::intersect($open, Hours::intervalsFor($own, $day)) : $open;
    }

    /** Intervalos libres del profesional ese día (hay que restar también bookings y time-offs). */
    public static function freeIntervals(Worker $worker, Branch $branch, Carbon $day, ?int $ignoreBookingId = null): array
    {
        $work = self::workingIntervals($worker, $branch, $day);
        if (!$work) {
            return [$work, []];
        }

        $from = $day->copy()->startOfDay();
        $to = $day->copy()->endOfDay();
        $busy = [];

        TimeOff::where('tenant_id', $worker->tenant_id)
            ->where(fn ($q) => $q->whereNull('worker_id')->orWhere('worker_id', $worker->id))
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->get()->each(function ($t) use (&$busy) {
                $busy[] = [$t->starts_at->copy(), $t->ends_at->copy()];
            });

        // Por profesional (no por sucursal): no puede estar en dos lugares a la vez.
        Booking::where('tenant_id', $worker->tenant_id)->where('worker_id', $worker->id)->blocking()
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->where('starts_at', '<', $to)->where('blocks_until', '>', $from)
            ->get()->each(function ($b) use (&$busy) {
                $busy[] = [$b->starts_at->copy(), $b->blocks_until->copy()];
            });

        return [$work, Hours::subtract($work, Hours::merge($busy))];
    }

    /** ¿Cabe la atención (duración + descanso) empezando en $start dentro de lo libre? */
    public static function fits(array $work, array $free, Carbon $start, int $duration, int $buffer): bool
    {
        $end = $start->copy()->addMinutes($duration);
        $endBuf = $end->copy()->addMinutes($buffer);

        foreach ($free as [$fs, $fe]) {
            if ($start->lt($fs) || $end->gt($fe)) {
                continue;
            }
            if ($endBuf->lte($fe)) {
                return true;
            }
            // El descanso puede exceder la hora de cierre, pero no pisar otra reserva/ausencia.
            foreach ($work as [, $we]) {
                if ($fe->eq($we) && $end->lte($fe)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Horarios disponibles de un día. $worker null = «cualquiera disponible».
     * Devuelve [['time' => '09:30', 'starts_at' => Carbon, 'worker_ids' => [..]], ...]
     */
    public static function slots(Service $service, Branch $branch, ?Worker $worker, Carbon $day, bool $public = true, ?int $ignoreBookingId = null): array
    {
        $settings = OfficeSettings::forTenant($service->tenant_id);
        $day = $day->copy()->startOfDay();

        if ($public) {
            if ($day->gt(now()->addDays((int) ($settings->max_advance_days ?: 60))->endOfDay())) {
                return [];
            }
            $earliest = now()->addHours((int) ($settings->min_notice_hours ?? 2));
        } else {
            $earliest = null;
        }

        $workers = $worker ? collect([$worker]) : self::eligibleWorkers($service, $branch, $public);
        if (!$worker && !($settings->auto_assign_worker ?? true) && $public) {
            return [];
        }

        $step = max(5, (int) ($settings->slot_step_minutes ?: 15));
        $duration = (int) $service->duration_minutes;
        $buffer = (int) $service->buffer_minutes;
        $slots = [];

        foreach ($workers as $w) {
            [$work, $free] = self::freeIntervals($w, $branch, $day, $ignoreBookingId);
            foreach ($free as [$fs, $fe]) {
                // Grilla alineada al reloj (múltiplos del paso), no al hueco que dejó otra reserva.
                $t = $fs->copy();
                $over = ($t->hour * 60 + $t->minute) % $step;
                if ($over || $t->second) {
                    $t->setTime($t->hour, $t->minute, 0)->addMinutes($step - $over);
                }
                for (; $t->copy()->addMinutes($duration)->lte($fe); $t->addMinutes($step)) {
                    if ($earliest && $t->lt($earliest)) {
                        continue;
                    }
                    if (self::fits($work, $free, $t, $duration, $buffer)) {
                        $key = $t->format('H:i');
                        $slots[$key]['time'] = $key;
                        $slots[$key]['starts_at'] = $t->copy();
                        $slots[$key]['worker_ids'][] = $w->id;
                    }
                }
            }
        }

        ksort($slots);

        return array_values($slots);
    }

    /** Días del rango con al menos un horario libre (para pintar el calendario). */
    public static function availableDays(Service $service, Branch $branch, ?Worker $worker, Carbon $from, int $days = 31, bool $public = true): array
    {
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i);
            if (self::slots($service, $branch, $worker, $d, $public)) {
                $out[] = $d->toDateString();
            }
        }

        return $out;
    }
}
