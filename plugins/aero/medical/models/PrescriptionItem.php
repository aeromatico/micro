<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\GuardsSignedNote;
use Aero\Medical\Classes\TenantOwned;
use Model;

class PrescriptionItem extends Model
{
    use TenantOwned;
    use GuardsSignedNote;

    public $table = 'aero_medical_prescription_items';

    public $fillable = ['tenant_id', 'prescription_id', 'position', 'source', 'medication', 'dose', 'route', 'frequency', 'duration', 'quantity', 'instructions'];

    public $casts = [
        'medication' => 'encrypted', 'dose' => 'encrypted', 'route' => 'encrypted', 'frequency' => 'encrypted',
        'duration' => 'encrypted', 'quantity' => 'encrypted', 'instructions' => 'encrypted',
    ];

    public $belongsTo = ['prescription' => [Prescription::class, 'key' => 'prescription_id']];

    protected function noteId(): ?int
    {
        return Prescription::where('id', $this->prescription_id)->value('consultation_id');
    }

    public function beforeSave(): void
    {
        $this->assertNoteOpen($this->noteId());
    }

    public function beforeDelete(): void
    {
        $this->assertNoteOpen($this->noteId());
    }
}
