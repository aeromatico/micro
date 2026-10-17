<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Audit;
use Aero\Medical\Classes\TenantOwned;
use Model;

/** Documento generado para un paciente. Borrador editable; emitido = inmutable, con huella. */
class MedicalDocument extends Model
{
    use \October\Rain\Database\Traits\SoftDelete;
    use TenantOwned;

    public $table = 'aero_medical_documents';

    public $fillable = ['tenant_id', 'patient_id', 'consultation_id', 'template_id', 'kind', 'title', 'body', 'user_id', 'professional_name'];

    public $casts = ['body' => 'encrypted'];

    protected $dates = ['issued_at'];

    public $belongsTo = ['patient' => [Patient::class, 'key' => 'patient_id']];

    public function isIssued(): bool
    {
        return $this->status === 'issued';
    }

    public function getKindLabelAttribute(): string
    {
        return DocumentTemplate::KINDS[$this->kind] ?? (string) $this->kind;
    }

    public function computeHash(): string
    {
        return hash('sha256', json_encode([
            (int) $this->tenant_id, (int) $this->patient_id, $this->kind, $this->title, $this->body, (int) $this->user_id,
            $this->professional_name, $this->issued_at?->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function integrityOk(): bool
    {
        return $this->isIssued() && $this->content_hash && hash_equals($this->content_hash, $this->computeHash());
    }

    public function getShortHashAttribute(): string
    {
        return strtoupper(substr((string) $this->content_hash, 0, 12));
    }

    public function beforeUpdate(): void
    {
        if ($this->getOriginal('status') === 'issued') {
            throw new \ApplicationException('El documento ya fue emitido y no se puede modificar. Genere uno nuevo.');
        }
    }

    public function beforeDelete(): void
    {
        if ($this->isIssued()) {
            throw new \ApplicationException('Un documento emitido no se puede eliminar.');
        }
    }

    public function beforeCreate(): void
    {
        $this->status = $this->status ?: 'draft';
    }

    public function afterCreate(): void
    {
        Audit::record('create', $this->tenant_id, $this->patient_id, 'document', $this->id);
    }
}
