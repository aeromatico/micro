<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Resultado de laboratorio anotado a mano (fase 4 añade archivos adjuntos). Editable y auditado. */
class LabResult extends Model
{
    use TenantOwned;

    public $table = 'aero_medical_lab_results';

    public $fillable = ['tenant_id', 'patient_id', 'consultation_id', 'analyte_id', 'analyte_name', 'unit', 'value', 'measured_on', 'note', 'user_id'];

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
}
