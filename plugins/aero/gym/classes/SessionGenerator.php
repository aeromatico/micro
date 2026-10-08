<?php namespace Aero\Gym\Classes;

use Aero\Gym\Models\ClassSession;
use Aero\Gym\Models\Schedule;
use Carbon\Carbon;

class SessionGenerator
{
    /** Crea las sesiones que falten (idempotente por schedule_id + starts_at). */
    public function generate(int $days = 14, ?int $tenantId = null): int
    {
        $created = 0;

        Schedule::with('classType')->where('is_active', true)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get()->each(function (Schedule $s) use ($days, &$created) {
                if (!$s->classType || !$s->classType->is_active) {
                    return;
                }
                for ($i = 0; $i <= $days; $i++) {
                    $day = today()->addDays($i);
                    if ($day->dayOfWeekIso !== (int) $s->weekday) {
                        continue;
                    }
                    $starts = Carbon::parse($day->toDateString() . ' ' . $s->start_time);
                    if ($starts->isPast()) {
                        continue;
                    }
                    $session = ClassSession::firstOrCreate(
                        ['schedule_id' => $s->id, 'starts_at' => $starts],
                        [
                            'tenant_id'     => $s->tenant_id,
                            'class_type_id' => $s->class_type_id,
                            'instructor_id' => $s->instructor_id,
                            'ends_at'       => $starts->copy()->addMinutes($s->classType->duration_minutes),
                            'capacity'      => $s->capacity ?: $s->classType->default_capacity,
                            'room'          => $s->room,
                            'status'        => 'scheduled',
                        ]
                    );
                    $created += (int) $session->wasRecentlyCreated;
                }
            });

        return $created;
    }
}
