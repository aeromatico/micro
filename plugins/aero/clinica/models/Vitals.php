<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\TenantOwned;
use Aero\Clinica\Classes\VitalsSchema;
use Model;

/** Signos vitales de una consulta. Se bloquean cuando la nota se firma. */
class Vitals extends Model
{
    use TenantOwned;

    public $table = 'aero_clinica_vitals';

    public $fillable = [
        'tenant_id', 'patient_id', 'consultation_id', 'measured_at', 'systolic', 'diastolic', 'heart_rate', 'resp_rate',
        'temperature_c', 'spo2', 'weight_kg', 'height_cm', 'mucous', 'crt_seconds', 'body_condition',
    ];

    protected $dates = ['measured_at'];

    public $belongsTo = [
        'consultation' => [Consultation::class, 'key' => 'consultation_id'],
        'patient'      => [Patient::class, 'key' => 'patient_id'],
    ];

    public function beforeSave(): void
    {
        $this->guardSigned();

        $this->bmi = VitalsSchema::bmi($this->weight_kg ? (float) $this->weight_kg : null, $this->height_cm ? (float) $this->height_cm : null);
    }

    public function beforeDelete(): void
    {
        $this->guardSigned();
    }

    protected function guardSigned(): void
    {
        if ($this->consultation_id && Consultation::where('id', $this->consultation_id)->where('status', 'signed')->exists()) {
            throw new \ApplicationException('La nota está firmada: sus signos vitales no se pueden modificar.');
        }
    }
}
