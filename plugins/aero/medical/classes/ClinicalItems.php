<?php namespace Aero\Medical\Classes;

use Aero\Medical\Models\Analyte;
use Aero\Medical\Models\Cie10;
use Aero\Medical\Models\Consultation;
use Aero\Medical\Models\Diagnosis;
use Aero\Medical\Models\DiagnosisCatalog;
use Aero\Medical\Models\ExamCatalog;
use Aero\Medical\Models\LabResult;
use Aero\Medical\Models\Liname;
use Aero\Medical\Models\Medication;
use Aero\Medical\Models\Order;
use Aero\Medical\Models\OrderItem;
use Aero\Medical\Models\Patient;
use Aero\Medical\Models\Prescription;
use Aero\Medical\Models\PrescriptionItem;
use Aero\Medical\Models\Questionnaire;
use Aero\Medical\Models\QuestionnaireAnswer;

/**
 * Diagnósticos, receta, órdenes y cuestionarios de una nota, más el laboratorio
 * manual del paciente. Solo el autor de un borrador toca la nota; lo firmado no
 * se modifica (además lo impone cada modelo con GuardsSignedNote).
 */
class ClinicalItems
{
    public const FIELD_MAX = 2000;

    // ---- Diagnósticos ----

    /**
     * @param array{source?:string,code?:?string,label?:string,certainty?:string,primary?:bool} $in
     */
    public function addDiagnosis(Consultation $c, array $in, $user): Diagnosis
    {
        $this->assertOpen($c, $user);
        $mode = $c->patient->kind;
        $source = (string) ($in['source'] ?? 'free');

        if ($source === 'cie10') {
            if ($mode === Mode::ANIMAL) {
                throw new MedicalException('CIE-10 no aplica en modo animal: use el catálogo del negocio o texto libre.');
            }
            // El título sale del catálogo oficial, no de lo que mande el cliente.
            $row = Cie10::where('code', strtoupper((string) ($in['code'] ?? '')))->first();
            if (!$row) {
                throw new MedicalException('Código CIE-10 no encontrado.');
            }
            $code = $row->code;
            $label = $row->title;
        } elseif ($source === 'custom') {
            $row = DiagnosisCatalog::forTenant((int) $c->tenant_id)->where('source', 'custom')->find((int) ($in['catalog_id'] ?? 0));
            if (!$row) {
                throw new MedicalException('Diagnóstico del catálogo no encontrado.');
            }
            $code = $row->code;
            $label = $row->label;
        } else {
            $source = 'free';
            $code = null;
            $label = trim((string) ($in['label'] ?? ''));
            if ($label === '') {
                throw new MedicalException('Escriba el diagnóstico.');
            }
        }

        $label = mb_substr($label, 0, 500);
        if (Diagnosis::where('consultation_id', $c->id)->where('source', $source)->where('code', $code)->where('label', $label)->exists()) {
            throw new MedicalException('Ese diagnóstico ya está en la nota.');
        }

        $certainty = array_key_exists($in['certainty'] ?? '', Diagnosis::CERTAINTY) ? $in['certainty'] : 'presumptive';
        $first = !Diagnosis::where('consultation_id', $c->id)->exists();
        $position = (int) Diagnosis::where('consultation_id', $c->id)->max('position') + 1;

        $d = Diagnosis::create([
            'tenant_id' => $c->tenant_id, 'patient_id' => $c->patient_id, 'consultation_id' => $c->id, 'source' => $source,
            'code' => $code, 'label' => $label, 'is_primary' => $first, 'certainty' => $certainty, 'position' => $position,
        ]);

        if (!$first && !empty($in['primary'])) {
            $this->setPrimary($c, $d, $user);
        }

        return $d;
    }

    public function setPrimary(Consultation $c, Diagnosis $d, $user): void
    {
        $this->assertOpen($c, $user);
        $this->owns($c, $d);
        foreach (Diagnosis::where('consultation_id', $c->id)->where('is_primary', true)->where('id', '!=', $d->id)->get() as $o) {
            $o->is_primary = false;
            $o->save();
        }
        $d->is_primary = true;
        $d->save();
    }

    public function setCertainty(Consultation $c, Diagnosis $d, string $certainty, $user): void
    {
        $this->assertOpen($c, $user);
        $this->owns($c, $d);
        if (!isset(Diagnosis::CERTAINTY[$certainty])) {
            throw new MedicalException('Certeza no válida.');
        }
        $d->certainty = $certainty;
        $d->save();
    }

    public function removeDiagnosis(Consultation $c, Diagnosis $d, $user): void
    {
        $this->assertOpen($c, $user);
        $this->owns($c, $d);
        $wasPrimary = $d->is_primary;
        $d->delete();

        if ($wasPrimary && ($next = Diagnosis::where('consultation_id', $c->id)->orderBy('position')->first())) {
            $next->is_primary = true;
            $next->save();
        }
    }

    /** Al firmar: los diagnósticos usados alimentan los Frecuentes del negocio. */
    public function bumpFrequents(Consultation $c): void
    {
        foreach (Diagnosis::where('consultation_id', $c->id)->get() as $d) {
            DiagnosisCatalog::bump((int) $c->tenant_id, $d->source, $d->code, $d->label);
        }
    }

    /** Resultados de búsqueda para el selector: Frecuentes (si no hay texto) o catálogo según el modo. */
    public function searchDiagnoses(int $tenantId, ?string $mode, string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['frequent' => DiagnosisCatalog::frequent($tenantId, $mode), 'cie' => collect(), 'custom' => collect()];
        }

        $custom = DiagnosisCatalog::forTenant($tenantId)->where('source', 'custom')
            ->where(fn ($w) => $w->where('label', 'like', '%' . addcslashes($q, '%_\\') . '%')->orWhere('code', 'like', addcslashes($q, '%_\\') . '%'))
            ->orderByDesc('pinned')->orderByDesc('uses')->limit(10)->get();

        return ['frequent' => collect(), 'cie' => $mode === Mode::ANIMAL ? collect() : Cie10::search($q), 'custom' => $custom];
    }

    // ---- Receta ----

    public function addPrescriptionItem(Consultation $c, array $in, $user): PrescriptionItem
    {
        $this->assertOpen($c, $user);

        $source = (string) ($in['source'] ?? 'free');
        $name = trim((string) ($in['medication'] ?? ''));
        if ($source === 'liname' && ($ref = Liname::find((int) ($in['ref_id'] ?? 0)))) {
            $name = trim($ref->name . ' ' . $ref->concentration . ($ref->form ? ' (' . $ref->form . ')' : ''));
        } elseif ($source === 'custom' && ($ref = Medication::forTenant((int) $c->tenant_id)->find((int) ($in['ref_id'] ?? 0)))) {
            $name = trim($ref->name . ' ' . $ref->concentration . ($ref->form ? ' (' . $ref->form . ')' : ''));
        } else {
            $source = 'free';
        }
        if ($name === '') {
            throw new MedicalException('Indique el medicamento.');
        }

        $rx = Prescription::firstOrCreate(['consultation_id' => $c->id], ['tenant_id' => $c->tenant_id, 'patient_id' => $c->patient_id]);

        return PrescriptionItem::create([
            'tenant_id' => $c->tenant_id, 'prescription_id' => $rx->id, 'source' => $source,
            'position' => (int) PrescriptionItem::where('prescription_id', $rx->id)->max('position') + 1,
            'medication' => $this->t($name),
            'dose' => $this->t($in['dose'] ?? null), 'route' => $this->t($in['route'] ?? null), 'frequency' => $this->t($in['frequency'] ?? null),
            'duration' => $this->t($in['duration'] ?? null), 'quantity' => $this->t($in['quantity'] ?? null), 'instructions' => $this->t($in['instructions'] ?? null),
        ]);
    }

    public function removePrescriptionItem(Consultation $c, int $itemId, $user): void
    {
        $this->assertOpen($c, $user);
        $rx = Prescription::where('consultation_id', $c->id)->first();
        $item = $rx ? PrescriptionItem::where('prescription_id', $rx->id)->find($itemId) : null;
        if (!$item) {
            throw new MedicalException('Medicamento no encontrado en la receta.');
        }
        $item->delete();
    }

    // ---- Órdenes ----

    public function addOrderItem(Consultation $c, array $in, $user): OrderItem
    {
        $this->assertOpen($c, $user);

        $name = trim((string) ($in['exam_name'] ?? ''));
        $category = 'lab';
        if (!empty($in['catalog_id']) && ($ex = ExamCatalog::forTenant((int) $c->tenant_id)->where('is_active', true)->find((int) $in['catalog_id']))) {
            $name = $ex->name;
            $category = $ex->category;
        }
        if ($name === '') {
            throw new MedicalException('Indique el examen.');
        }

        $order = Order::firstOrCreate(['consultation_id' => $c->id], ['tenant_id' => $c->tenant_id, 'patient_id' => $c->patient_id]);

        return OrderItem::create([
            'tenant_id' => $c->tenant_id, 'order_id' => $order->id, 'category' => $category,
            'position' => (int) OrderItem::where('order_id', $order->id)->max('position') + 1,
            'exam_name' => $this->t($name), 'instructions' => $this->t($in['instructions'] ?? null),
        ]);
    }

    public function removeOrderItem(Consultation $c, int $itemId, $user): void
    {
        $this->assertOpen($c, $user);
        $order = Order::where('consultation_id', $c->id)->first();
        $item = $order ? OrderItem::where('order_id', $order->id)->find($itemId) : null;
        if (!$item) {
            throw new MedicalException('Examen no encontrado en la orden.');
        }
        $item->delete();
    }

    // ---- Cuestionarios ----

    /** Guarda (o reemplaza) la respuesta de un cuestionario en la nota; el puntaje lo calcula el código. */
    public function answerQuestionnaire(Consultation $c, Questionnaire $q, array $answers, $user): QuestionnaireAnswer
    {
        $this->assertOpen($c, $user);
        if ((int) $q->tenant_id !== (int) $c->tenant_id || !$q->is_active) {
            throw new MedicalException('Cuestionario no disponible.');
        }

        $definition = ['questions' => $q->questions, 'ranges' => $q->ranges];
        try {
            $r = Questionnaire::evaluate($definition, $answers);
        } catch (\ApplicationException $e) {
            throw new MedicalException($e->getMessage());
        }

        $row = QuestionnaireAnswer::firstOrNew(['consultation_id' => $c->id, 'questionnaire_id' => $q->id]);
        $row->fill([
            'tenant_id' => $c->tenant_id, 'patient_id' => $c->patient_id, 'questionnaire_name' => $q->name, 'definition' => $definition,
            'answers' => $r['answers'], 'score' => $r['score'], 'interpretation' => $r['interpretation'], 'user_id' => $user->id, 'answered_at' => now(),
        ]);
        $row->save();

        return $row;
    }

    public function removeAnswer(Consultation $c, int $answerId, $user): void
    {
        $this->assertOpen($c, $user);
        $row = QuestionnaireAnswer::where('consultation_id', $c->id)->find($answerId);
        if (!$row) {
            throw new MedicalException('Respuesta no encontrada.');
        }
        $row->delete();
    }

    // ---- Laboratorio manual (por paciente) ----

    public function addLab(Patient $p, array $in, $user): LabResult
    {
        $value = str_replace(',', '.', trim((string) ($in['value'] ?? '')));
        if ($value === '' || !is_numeric($value)) {
            throw new MedicalException('Indique el valor del resultado (número).');
        }

        $name = trim((string) ($in['analyte_name'] ?? ''));
        $unit = trim((string) ($in['unit'] ?? ''));
        $analyteId = null;
        if (!empty($in['analyte_id']) && ($a = Analyte::forTenant((int) $p->tenant_id)->find((int) $in['analyte_id']))) {
            $analyteId = $a->id;
            $name = $a->name;
            $unit = $unit !== '' ? $unit : (string) $a->unit;
        }
        if ($name === '') {
            throw new MedicalException('Indique qué se midió.');
        }

        try {
            $date = \Carbon\Carbon::parse($in['measured_on'] ?? now()->toDateString())->startOfDay();
        } catch (\Throwable $e) {
            throw new MedicalException('Fecha no válida.');
        }
        if ($date->isFuture()) {
            throw new MedicalException('La fecha del resultado no puede ser futura.');
        }

        return LabResult::create([
            'tenant_id' => $p->tenant_id, 'patient_id' => $p->id, 'analyte_id' => $analyteId, 'analyte_name' => mb_substr($name, 0, 255),
            'unit' => mb_substr($unit, 0, 32) ?: null, 'value' => $value, 'measured_on' => $date->toDateString(),
            'note' => $this->t($in['note'] ?? null), 'user_id' => $user->id,
        ]);
    }

    // ---- util ----

    protected function t($v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, self::FIELD_MAX);
    }

    protected function assertOpen(Consultation $c, $user): void
    {
        if ($c->isSigned()) {
            throw new MedicalException('La nota está firmada y no se puede modificar. Agregue una adenda.');
        }
        if ((int) $c->user_id !== (int) $user->id) {
            throw new MedicalException('Solo quien abrió la nota puede editarla.');
        }
    }

    protected function owns(Consultation $c, Diagnosis $d): void
    {
        if ((int) $d->consultation_id !== (int) $c->id || (int) $d->tenant_id !== (int) $c->tenant_id) {
            throw new MedicalException('Diagnóstico no encontrado en esta nota.');
        }
    }
}
