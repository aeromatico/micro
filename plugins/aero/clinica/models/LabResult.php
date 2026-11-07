<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\Audit;
use Aero\Clinica\Classes\TenantOwned;
use Model;

/** Resultado de laboratorio (numérico, con unidad y referencia del laboratorio). Distinto de la solicitud (OrderItem). Auditado. */
class LabResult extends Model
{
    use TenantOwned;

    public $table = 'aero_clinica_lab_results';

    public $fillable = ['tenant_id', 'patient_id', 'consultation_id', 'analyte_id', 'analyte_name', 'unit', 'value', 'ref_low', 'ref_high', 'lab_name', 'order_item_id', 'measured_on', 'note', 'user_id'];

    public $casts = ['note' => 'encrypted', 'measured_on' => 'date'];

    public $belongsTo = ['patient' => [Patient::class, 'key' => 'patient_id']];

    public function afterCreate(): void
    {
        Audit::record('create', $this->tenant_id, $this->patient_id, 'lab_result', $this->id);
    }

    public function afterUpdate(): void
    {
        Audit::record('update', $this->tenant_id, $this->patient_id, 'lab_result', $this->id, array_keys($this->getChanges()));
    }

    public function afterDelete(): void
    {
        Audit::record('delete', $this->tenant_id, $this->patient_id, 'lab_result', $this->id);
    }

    /** Clave de métrica para los rangos. */
    public function metricKey(): string
    {
        return $this->analyte_id ? 'analyte:' . $this->analyte_id : 'name:' . md5(mb_strtolower($this->analyte_name));
    }

    /** ▼ low / ▲ high: la referencia del propio laboratorio manda; si no hay, la del negocio (por sexo). */
    public function flag(?string $sex = null): ?string
    {
        if ($this->value === null) {
            return null;
        }
        if ($this->ref_low !== null || $this->ref_high !== null) {
            if ($this->ref_low !== null && (float) $this->value < (float) $this->ref_low) {
                return 'low';
            }

            return $this->ref_high !== null && (float) $this->value > (float) $this->ref_high ? 'high' : null;
        }

        return \Aero\Clinica\Classes\Ranges::flag((int) $this->tenant_id, $sex, $this->metricKey(), $this->value);
    }

    public function referenceText(): string
    {
        $f = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        if ($this->ref_low !== null && $this->ref_high !== null) {
            return $f($this->ref_low) . ' – ' . $f($this->ref_high);
        }

        return $this->ref_low !== null ? '≥ ' . $f($this->ref_low) : ($this->ref_high !== null ? '≤ ' . $f($this->ref_high) : '');
    }
}
