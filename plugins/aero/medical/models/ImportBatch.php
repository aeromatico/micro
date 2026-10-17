<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/** Lote de archivos importados que esperan asociarse a un paciente. */
class ImportBatch extends Model
{
    use TenantOwned;

    public $table = 'aero_medical_imports';

    public $fillable = ['tenant_id', 'user_id', 'label', 'total'];

    public $hasMany = ['files' => [MedicalFile::class, 'key' => 'import_id']];

    public function pendingCount(): int
    {
        return MedicalFile::where('import_id', $this->id)->whereIn('match_status', ['pending', 'suggested'])->count();
    }
}
