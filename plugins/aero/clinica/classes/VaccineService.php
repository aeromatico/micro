<?php namespace Aero\Clinica\Classes;

use Aero\Clinica\Models\Patient;
use Aero\Clinica\Models\Vaccination;
use Carbon\Carbon;

/**
 * Registro de dosis aplicadas y calendario del paciente. Sin catálogo ni esquema de fábrica: en Bolivia el esquema
 * depende del programa, del proveedor y del criterio médico, así que el nombre, la dosis y la próxima fecha los
 * indica el profesional. Sí sugiere nombres de vacunas que la clínica ya registró.
 */
class VaccineService
{
    /**
     * @param array{vaccine_name?:string,dose_label?:string,applied_on?:string,lot?:string,manufacturer?:string,route_site?:string,next_due_on?:string,notes?:string} $in
     */
    public function apply(Patient $p, array $in, $user): Vaccination
    {
        $tenantId = (int) $p->tenant_id;
        $name = trim((string) ($in['vaccine_name'] ?? ''));
        if ($name === '') {
            throw new ClinicaException('Indique la vacuna.');
        }

        $appliedOn = $this->date($in['applied_on'] ?? null, 'Fecha de aplicación') ?? now()->startOfDay();
        if ($appliedOn->isFuture()) {
            throw new ClinicaException('La fecha de aplicación no puede ser futura.');
        }

        $dose = trim((string) ($in['dose_label'] ?? ''));
        $next = $this->date($in['next_due_on'] ?? null, 'Próxima dosis');
        if ($next && $next->lt($appliedOn)) {
            throw new ClinicaException('La próxima dosis no puede ser anterior a la aplicación.');
        }

        $clean = fn (string $k, int $max) => trim((string) ($in[$k] ?? '')) !== '' ? mb_substr(trim((string) $in[$k]), 0, $max) : null;

        return Vaccination::create([
            'tenant_id' => $tenantId, 'patient_id' => $p->id, 'vaccine_name' => mb_substr($name, 0, 255),
            'dose_label' => $dose !== '' ? mb_substr($dose, 0, 255) : null, 'applied_on' => $appliedOn->toDateString(),
            'lot' => $clean('lot', 64), 'manufacturer' => $clean('manufacturer', 128), 'route_site' => $clean('route_site', 64),
            'next_due_on' => $next?->toDateString(), 'applied_by' => $user->id,
            'notes' => $clean('notes', 2000),
        ]);
    }

    /** Nombres de vacunas que la clínica ya registró (para sugerir al escribir). @return string[] */
    public static function knownNames(int $tenantId, int $limit = 50): array
    {
        return Vaccination::where('tenant_id', $tenantId)->select('vaccine_name')->groupBy('vaccine_name')
            ->orderByRaw('max(id) desc')->limit($limit)->pluck('vaccine_name')->all();
    }

    /**
     * Calendario del paciente: dosis aplicadas y lo pendiente (la próxima dosis de la última aplicación de cada vacuna).
     * @return array{applied:\Illuminate\Support\Collection, due:array}
     */
    public function calendar(Patient $p): array
    {
        $tenantId = (int) $p->tenant_id;
        $applied = Vaccination::where('tenant_id', $tenantId)->where('patient_id', $p->id)->orderByDesc('applied_on')->orderByDesc('id')->get();

        $due = [];
        foreach ($applied->groupBy(fn ($v) => mb_strtolower($v->vaccine_name)) as $group) {
            $last = $group->first();
            if ($last->next_due_on) {
                $due[] = ['vaccination' => $last, 'overdue' => $last->isOverdue(), 'due_on' => $last->next_due_on];
            }
        }
        usort($due, fn ($a, $b) => $a['due_on']->timestamp <=> $b['due_on']->timestamp);

        return ['applied' => $applied, 'due' => $due];
    }

    protected function date($v, string $label): ?Carbon
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->startOfDay();
        } catch (\Throwable $e) {
            throw new ClinicaException($label . ' no es una fecha válida.');
        }
    }
}
