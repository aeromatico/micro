<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\TenantOwned;
use Model;

/** Lote de archivos importados que esperan asociarse a un paciente. */
class ImportBatch extends Model
{
    use TenantOwned;

    public $table = 'aero_clinica_imports';

    public $fillable = ['tenant_id', 'user_id', 'label', 'total'];

    public $hasMany = ['files' => [ClinicaFile::class, 'key' => 'import_id']];

    public function pendingCount(): int
    {
        return ClinicaFile::where('import_id', $this->id)->whereIn('match_status', ['pending', 'suggested'])->count();
    }
}
