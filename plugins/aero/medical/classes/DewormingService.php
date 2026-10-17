<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\ClinicSettings;
use Aero\Medical\Models\Deworming;
use Aero\Medical\Models\Patient;
use Carbon\Carbon;

/** Desparasitaciones del animal: registro, próxima fecha (a mano o por el intervalo del negocio) y calendario. */
class DewormingService
{
    use AnimalOnly;

    /**
     * @param array{product?:string,kind?:string,dose?:string,applied_on?:string,next_due_on?:string,lot?:string,notes?:string} $in
     */
    public function apply(Patient $p, array $in, $user): Deworming
    {
        $this->assertAnimal($p);

        $product = trim((string) ($in['product'] ?? ''));
        if ($product === '') {
            throw new MedicalException('Indique el producto.');
        }

        $appliedOn = $this->date($in['applied_on'] ?? null, 'Fecha de aplicación') ?? now()->startOfDay();
        if ($appliedOn->isFuture()) {
            throw new MedicalException('La fecha de aplicación no puede ser futura.');
        }

        $next = $this->date($in['next_due_on'] ?? null, 'Próxima desparasitación');
        if (!$next) {
            // Solo si el negocio definió su intervalo; el sistema no inventa uno.
            $days = (int) ClinicSettings::where('tenant_id', $p->tenant_id)->value('deworming_interval_days');
            $next = $days > 0 ? $appliedOn->copy()->addDays($days) : null;
        }
        if ($next && $next->lt($appliedOn)) {
            throw new MedicalException('La próxima desparasitación no puede ser anterior a la aplicación.');
        }

        $kind = array_key_exists($in['kind'] ?? '', Deworming::KINDS) ? $in['kind'] : null;
        $clean = fn ($v, $max) => trim((string) $v) !== '' ? mb_substr(trim((string) $v), 0, $max) : null;

        return Deworming::create([
            'tenant_id' => $p->tenant_id, 'patient_id' => $p->id, 'product' => mb_substr($product, 0, 255), 'kind' => $kind,
            'dose' => $clean($in['dose'] ?? '', 100), 'applied_on' => $appliedOn->toDateString(), 'next_due_on' => $next?->toDateString(),
            'lot' => $clean($in['lot'] ?? '', 64), 'applied_by' => $user->id, 'notes' => $clean($in['notes'] ?? '', 2000),
        ]);
    }

    /** @return array{applied:\Illuminate\Support\Collection, due:array} lo pendiente es la próxima fecha de cada producto (solo cuenta su última aplicación) */
    public function calendar(Patient $p): array
    {
        $applied = Deworming::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->orderByDesc('applied_on')->orderByDesc('id')->get();

        $due = [];
        foreach ($applied->groupBy(fn ($d) => mb_strtolower($d->product)) as $group) {
            $last = $group->first();
            if ($last->next_due_on) {
                $due[] = ['deworming' => $last, 'overdue' => $last->isOverdue(), 'due_on' => $last->next_due_on];
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
            throw new MedicalException($label . ' no es una fecha válida.');
        }
    }
}
