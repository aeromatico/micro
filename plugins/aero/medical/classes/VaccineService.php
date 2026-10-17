<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Patient;
use Aero\Medical\Models\Vaccination;
use Aero\Medical\Models\Vaccine;
use Carbon\Carbon;

/** Dosis aplicadas, próxima dosis calculada desde el esquema del negocio y calendario del paciente. */
class VaccineService
{
    /**
     * @param array{vaccine_id?:mixed,vaccine_name?:string,dose_label?:string,applied_on?:string,lot?:string,next_due_on?:string,notes?:string} $in
     */
    public function apply(Patient $p, array $in, $user): Vaccination
    {
        $tenantId = (int) $p->tenant_id;
        $vaccine = null;
        $name = trim((string) ($in['vaccine_name'] ?? ''));

        if (!empty($in['vaccine_id'])) {
            $vaccine = Vaccine::forTenant($tenantId)->find((int) $in['vaccine_id']);
            if (!$vaccine) {
                throw new MedicalException('Vacuna no encontrada en el catálogo del negocio.');
            }
            $name = $vaccine->name;
        }
        if ($name === '') {
            throw new MedicalException('Indique la vacuna.');
        }

        $appliedOn = $this->date($in['applied_on'] ?? null, 'Fecha de aplicación') ?? now()->startOfDay();
        if ($appliedOn->isFuture()) {
            throw new MedicalException('La fecha de aplicación no puede ser futura.');
        }

        // Dosis que corresponde: la siguiente del esquema según las ya aplicadas de esta vacuna.
        $done = $vaccine ? Vaccination::where('tenant_id', $tenantId)->where('patient_id', $p->id)->where('vaccine_id', $vaccine->id)->count() : 0;
        $dose = trim((string) ($in['dose_label'] ?? ''));
        if ($dose === '' && $vaccine && ($d = $vaccine->doseAt($done))) {
            $dose = $d['label'];
        } elseif ($dose === '' && $vaccine && $done > 0) {
            $dose = 'Refuerzo';
        }

        // Próxima dosis: la indicada a mano, o la que sale del esquema (dosis siguiente o refuerzo).
        $next = $this->date($in['next_due_on'] ?? null, 'Próxima dosis');
        if (!$next && $vaccine && ($days = $vaccine->daysAfterDose($done)) !== null) {
            $next = $appliedOn->copy()->addDays($days);
        }
        if ($next && $next->lt($appliedOn)) {
            throw new MedicalException('La próxima dosis no puede ser anterior a la aplicación.');
        }

        return Vaccination::create([
            'tenant_id' => $tenantId, 'patient_id' => $p->id, 'vaccine_id' => $vaccine?->id, 'vaccine_name' => mb_substr($name, 0, 255),
            'dose_label' => $dose !== '' ? mb_substr($dose, 0, 255) : null, 'applied_on' => $appliedOn->toDateString(),
            'lot' => trim((string) ($in['lot'] ?? '')) !== '' ? mb_substr(trim((string) $in['lot']), 0, 64) : null,
            'next_due_on' => $next?->toDateString(), 'applied_by' => $user->id,
            'notes' => trim((string) ($in['notes'] ?? '')) !== '' ? mb_substr(trim((string) $in['notes']), 0, 2000) : null,
        ]);
    }

    /**
     * Calendario del paciente: dosis aplicadas y lo pendiente. Lo pendiente es la próxima dosis de cada vacuna
     * (solo la última aplicación de cada una cuenta) y las vacunas del catálogo aplicables aún sin iniciar.
     * @return array{applied:\Illuminate\Support\Collection, due:array, not_started:\Illuminate\Support\Collection}
     */
    public function calendar(Patient $p): array
    {
        $tenantId = (int) $p->tenant_id;
        $applied = Vaccination::where('tenant_id', $tenantId)->where('patient_id', $p->id)->orderByDesc('applied_on')->orderByDesc('id')->get();

        $due = [];
        foreach ($applied->groupBy(fn ($v) => $v->vaccine_id ?: 'n:' . mb_strtolower($v->vaccine_name)) as $group) {
            $last = $group->first();
            if ($last->next_due_on) {
                $due[] = ['vaccination' => $last, 'overdue' => $last->isOverdue(), 'due_on' => $last->next_due_on];
            }
        }
        usort($due, fn ($a, $b) => $a['due_on']->timestamp <=> $b['due_on']->timestamp);

        $startedIds = $applied->pluck('vaccine_id')->filter()->unique()->all();
        $notStarted = Vaccine::forTenant($tenantId)->where('is_active', true)->orderBy('name')->get()
            ->filter(fn ($v) => !in_array($v->id, $startedIds, true) && ($p->kind !== Mode::ANIMAL || $v->appliesTo($p->species)));

        return ['applied' => $applied, 'due' => $due, 'not_started' => $notStarted->values()];
    }

    protected function date($v, string $label): ?Carbon
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->startOfDay();
        } catch (\Throwable $e) {
            throw new MedicalException($label . ' no es una fecha válida.');
        }
    }
}
