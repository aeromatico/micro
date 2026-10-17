<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\ClinicSettings;
use Aero\Medical\Models\Deworming;
use Aero\Medical\Models\Vaccination;

/**
 * Recordatorios de próxima dosis. Solo para negocios que los activaron (apagado por defecto) y solo si la
 * dosis sigue pendiente: si ya se aplicó una posterior de esa vacuna, no se avisa. Cada dosis se avisa una vez.
 */
class Reminders
{
    /** @return int avisos enviados */
    public static function vaccines(): int
    {
        $sent = 0;

        foreach (ClinicSettings::where('vaccine_reminders', true)->where('enabled', true)->get() as $s) {
            $days = max(1, (int) $s->reminder_days_before);

            Vaccination::where('tenant_id', $s->tenant_id)->whereNotNull('next_due_on')->whereNull('reminder_sent_at')
                ->whereBetween('next_due_on', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
                ->with(['patient.customer'])->orderBy('next_due_on')->limit(500)->get()
                ->each(function (Vaccination $v) use (&$sent) {
                    if (!$v->patient || !$v->patient->is_active || static::alreadyApplied($v)) {
                        return;
                    }

                    // Solo ids: Aero.Api reenvía a webhooks lo que viaja en eventos aero.*.
                    \Event::fire('aero.medical.vaccineDue', [['vaccination_id' => $v->id, 'patient_id' => $v->patient_id, 'tenant_id' => $v->tenant_id]]);

                    if (Notifier::vaccineDue($v)) {
                        $v->forceFill(['reminder_sent_at' => now()])->saveQuietly();
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    /** @return int avisos enviados. Mismo criterio que las vacunas: negocio que lo activó, dosis pendiente, un aviso por fecha. */
    public static function dewormings(): int
    {
        $sent = 0;

        foreach (ClinicSettings::where('deworming_reminders', true)->where('enabled', true)->get() as $s) {
            $days = max(1, (int) $s->reminder_days_before);

            Deworming::where('tenant_id', $s->tenant_id)->whereNotNull('next_due_on')->whereNull('reminder_sent_at')
                ->whereBetween('next_due_on', [now()->startOfDay(), now()->addDays($days)->endOfDay()])
                ->with(['patient.customer'])->orderBy('next_due_on')->limit(500)->get()
                ->each(function (Deworming $d) use (&$sent) {
                    if (!$d->patient || !$d->patient->is_active || $d->patient->kind !== Mode::ANIMAL || static::dewormingDone($d)) {
                        return;
                    }

                    // Solo ids: Aero.Api reenvía a webhooks lo que viaja en eventos aero.*.
                    \Event::fire('aero.medical.dewormingDue', [['deworming_id' => $d->id, 'patient_id' => $d->patient_id, 'tenant_id' => $d->tenant_id]]);

                    if (Notifier::dewormingDue($d)) {
                        $d->forceFill(['reminder_sent_at' => now()])->saveQuietly();
                        $sent++;
                    }
                });
        }

        return $sent;
    }

    /** ¿Ya se aplicó el mismo producto después? Entonces el aviso quedó sin objeto. */
    public static function dewormingDone(Deworming $d): bool
    {
        return Deworming::where('tenant_id', $d->tenant_id)->where('patient_id', $d->patient_id)->where('id', '!=', $d->id)
            ->where('product', $d->product)->where('applied_on', '>', $d->applied_on)->exists();
    }

    /** ¿Ya hay una dosis posterior de la misma vacuna? Entonces el aviso quedó sin objeto. */
    public static function alreadyApplied(Vaccination $v): bool
    {
        return Vaccination::where('tenant_id', $v->tenant_id)->where('patient_id', $v->patient_id)
            ->where('id', '!=', $v->id)
            ->where(fn ($q) => $v->vaccine_id ? $q->where('vaccine_id', $v->vaccine_id) : $q->where('vaccine_name', $v->vaccine_name))
            ->where('applied_on', '>', $v->applied_on)->exists();
    }
}
