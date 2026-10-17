<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Visita de peluquería y spa (solo animales). Las fotos antes/después son archivos cifrados ligados a la visita. */
class GroomingVisit extends Model
{
    use \October\Rain\Database\Traits\SoftDelete;
    use TenantOwned;

    public $table = 'aero_medical_grooming_visits';

    public $fillable = ['tenant_id', 'patient_id', 'service', 'visited_on', 'notes', 'next_visit_on', 'performed_by', 'booking_id'];

    public $casts = ['notes' => 'encrypted', 'visited_on' => 'date', 'next_visit_on' => 'date'];

    public $belongsTo = ['patient' => [Patient::class, 'key' => 'patient_id']];

    public function photosOf(string $side)
    {
        return MedicalFile::where('grooming_id', $this->id)->where('kind', 'grooming_' . $side)->orderBy('id')->get();
    }

    public function afterCreate(): void
    {
        Audit::record('create', $this->tenant_id, $this->patient_id, 'grooming', $this->id);
    }

    public function afterUpdate(): void
    {
        Audit::record('update', $this->tenant_id, $this->patient_id, 'grooming', $this->id, array_keys($this->getChanges()));
    }

    public function afterDelete(): void
    {
        Audit::record('delete', $this->tenant_id, $this->patient_id, 'grooming', $this->id);
    }
}
