<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/**
 * Nota clínica (consulta o control). Borrador editable por su autor; al firmar
 * queda inmutable (nada se modifica ni se borra) y los cambios posteriores son
 * adendas. El texto va cifrado; content_hash permite comprobar que no se tocó.
 */
class Consultation extends Model
{
    use \October\Rain\Database\Traits\SoftDelete;
    use TenantOwned;

    public $table = 'aero_medical_consultations';

    public $fillable = [
        'tenant_id', 'patient_id', 'kind', 'template_id', 'template_name', 'sections', 'content', 'user_id', 'worker_id',
        'professional_name', 'booking_id', 'service_name', 'consulted_at',
    ];

    public $jsonable = ['sections'];

    public $casts = ['content' => 'encrypted:array'];

    protected $dates = ['consulted_at', 'last_autosave_at', 'signed_at'];

    public $belongsTo = [
        'patient' => [Patient::class, 'key' => 'patient_id'],
    ];

    public $hasOne = [
        'vitals'       => [Vitals::class, 'key' => 'consultation_id'],
        'prescription' => [Prescription::class, 'key' => 'consultation_id'],
        'exam_order'   => [Order::class, 'key' => 'consultation_id'],
    ];

    public $hasMany = [
        'addenda'   => [Addendum::class, 'key' => 'consultation_id', 'order' => 'id asc'],
        'diagnoses' => [Diagnosis::class, 'key' => 'consultation_id', 'order' => 'position asc'],
        'answers'   => [QuestionnaireAnswer::class, 'key' => 'consultation_id', 'order' => 'id asc'],
    ];

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    public function getKindLabelAttribute(): string
    {
        return \Aero\Medical\Classes\DefaultTemplates::KINDS[$this->kind] ?? (string) $this->kind;
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->isSigned() ? 'Firmada' : 'Borrador';
    }

    /** Secciones de la foto de plantilla, normalizadas. */
    public function sectionList(): array
    {
        return array_values(array_filter((array) $this->sections, fn ($s) => !empty($s['key'])));
    }

    public function beforeUpdate(): void
    {
        if ($this->getOriginal('status') === 'signed') {
            throw new \ApplicationException('La nota está firmada y no se puede modificar. Agregue una adenda.');
        }
    }

    public function beforeDelete(): void
    {
        if ($this->isSigned()) {
            throw new \ApplicationException('Una nota firmada no se puede eliminar.');
        }
    }

    /** Huella de lo firmado: contenido, signos vitales, autor y fechas. */
    public function computeHash(): string
    {
        $content = (array) $this->content;
        ksort($content);

        $vit = $this->vitals ? collect($this->vitals->only(['systolic', 'diastolic', 'heart_rate', 'resp_rate', 'temperature_c', 'spo2', 'weight_kg', 'height_cm', 'bmi', 'mucous', 'crt_seconds', 'body_condition']))
            ->map(fn ($v) => $v === null ? null : (string) $v)->all() : [];

        // Lo clínico estructurado entra en la huella solo si existe (las notas anteriores a la fase 3 siguen verificando).
        $extra = [];
        $dx = Diagnosis::where('consultation_id', $this->id)->orderBy('position')->orderBy('id')->get()
            ->map(fn ($d) => [$d->source, (string) $d->code, $d->label, (int) $d->is_primary, $d->certainty])->all();
        if ($dx) {
            $extra['diagnoses'] = $dx;
        }
        if ($rx = Prescription::where('consultation_id', $this->id)->first()) {
            $items = PrescriptionItem::where('prescription_id', $rx->id)->orderBy('position')->orderBy('id')->get()
                ->map(fn ($i) => [$i->medication, $i->dose, $i->route, $i->frequency, $i->duration, $i->quantity, $i->instructions])->all();
            if ($items || $rx->notes) {
                $extra['prescription'] = ['notes' => $rx->notes, 'items' => $items];
            }
        }
        if ($ord = Order::where('consultation_id', $this->id)->first()) {
            $items = OrderItem::where('order_id', $ord->id)->orderBy('position')->orderBy('id')->get()->map(fn ($i) => [$i->category, $i->exam_name, $i->instructions])->all();
            if ($items || $ord->notes) {
                $extra['orders'] = ['notes' => $ord->notes, 'items' => $items];
            }
        }
        $qa = QuestionnaireAnswer::where('consultation_id', $this->id)->orderBy('id')->get()
            ->map(fn ($a) => [(int) $a->questionnaire_id, $a->questionnaire_name, (string) (float) $a->score, (string) $a->interpretation, (array) $a->answers])->all();
        if ($qa) {
            $extra['questionnaires'] = $qa;
        }

        return hash('sha256', json_encode([
            'tenant'  => (int) $this->tenant_id,
            'patient' => (int) $this->patient_id,
            'kind'    => $this->kind,
            'author'  => (int) $this->user_id,
            'sections' => $this->sections,
            'content' => $content,
            'vitals'  => $vit,
            'at'      => $this->consulted_at?->toIso8601String(),
            'signed'  => $this->signed_at?->toIso8601String(),
        ] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** ¿Lo guardado sigue siendo lo que se firmó? */
    public function integrityOk(): bool
    {
        return $this->isSigned() && $this->content_hash && hash_equals($this->content_hash, $this->computeHash());
    }

    public function getShortHashAttribute(): string
    {
        return strtoupper(substr((string) $this->content_hash, 0, 12));
    }
}
