<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\GuardsSignedNote;
use Aero\Clinica\Classes\TenantOwned;
use Model;

/** Receta de una nota (una por nota, con varios medicamentos). */
class Prescription extends Model
{
    use TenantOwned;
    use GuardsSignedNote;

    public $table = 'aero_clinica_prescriptions';

    public $fillable = ['tenant_id', 'patient_id', 'consultation_id', 'notes'];

    public $casts = ['notes' => 'encrypted'];

    public $hasMany = ['items' => [PrescriptionItem::class, 'key' => 'prescription_id', 'order' => 'position asc']];

    public function beforeSave(): void
    {
        $this->assertNoteOpen($this->consultation_id);
    }

    public function beforeDelete(): void
    {
        $this->assertNoteOpen($this->consultation_id);
    }
}
