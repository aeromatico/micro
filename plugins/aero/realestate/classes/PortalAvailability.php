<?php namespace Aero\Realestate\Classes;

use Aero\Realestate\Models\Property;
use Aero\Realestate\Models\RealestateSettings;
use Aero\Realestate\Models\Visit;
use Carbon\Carbon;

/** Horarios de visita libres de una propiedad: horario de atención del tenant, sin choques con el agente ni con la propiedad. */
class PortalAvailability
{
    /** @return string[] horas «HH:MM» libres del día (vacío si no se atiende ese día o ya pasó el plazo). */
    public static function slots(Property $property, Carbon $date): array
    {
        $s = RealestateSettings::forTenant($property->tenant_id);
        $day = $date->copy()->startOfDay();

        if (!in_array((int) $day->isoWeekday(), $s->visit_days_list, true)
            || $day->lt(today()) || $day->gt(today()->addDays((int) ($s->visit_advance_days ?: 30)))) {
            return [];
        }

        $minutes = $s->visit_minutes;
        [$sh, $sm] = array_map('intval', explode(':', $s->visit_start_time ?: '09:00'));
        [$eh, $em] = array_map('intval', explode(':', $s->visit_end_time ?: '18:00'));
        $from = $day->copy()->setTime($sh, $sm);
        $to = $day->copy()->setTime($eh, $em);
        $earliest = now()->addHours((int) ($s->visit_notice_hours ?? 4));

        $busy = Visit::active()->where('tenant_id', $property->tenant_id)
            ->where(fn ($q) => $q->where('property_id', $property->id)->when($property->agent_id, fn ($w) => $w->orWhere('agent_id', $property->agent_id)))
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)->get(['starts_at', 'ends_at']);

        $slots = [];
        for ($t = $from->copy(); $t->copy()->addMinutes($minutes)->lte($to); $t->addMinutes($minutes)) {
            $end = $t->copy()->addMinutes($minutes);
            if ($t->lt($earliest) || $busy->contains(fn ($v) => $v->starts_at->lt($end) && $v->ends_at->gt($t))) {
                continue;
            }
            $slots[] = $t->format('H:i');
        }

        return $slots;
    }
}
