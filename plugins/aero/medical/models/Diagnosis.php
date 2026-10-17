<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\GuardsSignedNote;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Diagnóstico de una nota: principal/secundario y presuntivo/confirmado. Se bloquea al firmar. */
class Diagnosis extends Model
{
    use TenantOwned;
    use GuardsSignedNote;

    public $table = 'aero_medical_diagnoses';

    public $fillable = ['tenant_id', 'patient_id', 'consultation_id', 'source', 'code', 'label', 'is_primary', 'certainty', 'position'];

    public $casts = ['is_primary' => 'boolean'];

    public const CERTAINTY = ['presumptive' => 'Presuntivo', 'confirmed' => 'Confirmado'];

    public function beforeSave(): void
    {
        $this->assertNoteOpen($this->consultation_id);
    }

    public function beforeDelete(): void
    {
        $this->assertNoteOpen($this->consultation_id);
    }

    public function getCertaintyLabelAttribute(): string
    {
        return self::CERTAINTY[$this->certainty] ?? (string) $this->certainty;
    }

    public function getDisplayAttribute(): string
    {
        return trim(($this->code ? $this->code . ' ' : '') . $this->label);
    }
}
