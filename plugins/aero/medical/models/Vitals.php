<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\TenantOwned;
use Aero\Medical\Classes\VitalsSchema;
use Model;

/** Signos vitales de una consulta. Se bloquean cuando la nota se firma. */
class Vitals extends Model
{
    use TenantOwned;

    public $table = 'aero_medical_vitals';

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

        // IMC solo en humano; en animal es la condición corporal.
        $kind = $this->patient?->kind;
        $this->bmi = $kind === Mode::ANIMAL ? null : VitalsSchema::bmi($this->weight_kg ? (float) $this->weight_kg : null, $this->height_cm ? (float) $this->height_cm : null);
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
