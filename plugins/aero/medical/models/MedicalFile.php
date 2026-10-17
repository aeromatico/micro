<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\PrivateFiles;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Archivo clínico (laboratorio, imagen, historia anterior…). El contenido vive cifrado en disco. */
class MedicalFile extends Model
{
    use \October\Rain\Database\Traits\SoftDelete;
    use TenantOwned;

    public $table = 'aero_medical_files';

    public $fillable = [
        'tenant_id', 'patient_id', 'consultation_id', 'kind', 'title', 'original_name', 'mime', 'size', 'storage_name', 'sha256',
        'taken_on', 'notes', 'import_id', 'match_status', 'suggested_patient_id', 'suggestion_reason', 'uploaded_by',
    ];

    public $casts = ['title' => 'encrypted', 'original_name' => 'encrypted', 'notes' => 'encrypted', 'taken_on' => 'date'];

    public $belongsTo = ['patient' => [Patient::class, 'key' => 'patient_id']];

    public const KINDS = ['lab' => 'Laboratorio', 'imaging' => 'Imagen diagnóstica', 'history' => 'Historia anterior', 'other' => 'Otro'];

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? (string) $this->kind;
    }

    public function getDisplayNameAttribute(): string
    {
        return trim((string) $this->title) !== '' ? (string) $this->title : (string) $this->original_name;
    }

    public function contents(): string
    {
        return PrivateFiles::read((int) $this->tenant_id, $this->storage_name);
    }

    /** ¿El archivo en disco sigue siendo el que se subió? */
    public function integrityOk(): bool
    {
        try {
            return hash_equals($this->sha256, hash('sha256', $this->contents()));
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isInline(): bool
    {
        return in_array($this->mime, PrivateFiles::INLINE, true);
    }

    public function afterCreate(): void
    {
        Audit::record('create', $this->tenant_id, $this->patient_id, 'file', $this->id);
    }

    public function afterDelete(): void
    {
        Audit::record('delete', $this->tenant_id, $this->patient_id, 'file', $this->id);
    }
}
