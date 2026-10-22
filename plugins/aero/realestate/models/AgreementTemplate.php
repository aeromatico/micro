<?php namespace Aero\Realestate\Models;

use Model;

/**
 * Plantilla de contrato. tenant_id null = modelo base de la plataforma (solo lectura para las inmobiliarias,
 * que lo duplican). Un cambio en el texto de una plantilla ya revisada la devuelve a «borrador»: la revisión
 * legal vale para el texto exacto que se revisó.
 */
class AgreementTemplate extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_templates';

    public $fillable = ['tenant_id', 'type', 'name', 'body', 'is_active', 'legal_notes'];

    public $rules = [
        'type' => 'required|in:arras,rent,anticretico',
        'name' => 'required|string|max:255',
        'body' => 'required|string|min:20',
    ];

    public $attributes = ['is_active' => true, 'legal_status' => 'draft'];

    public $dates = ['reviewed_at'];

    public static array $types = ['arras' => 'Arras', 'rent' => 'Alquiler', 'anticretico' => 'Anticrético'];
    public static array $legal = ['draft' => 'Borrador (sin revisión legal)', 'reviewed' => 'Revisada legalmente'];

    public function scopeAvailable($query, ?int $tenantId)
    {
        return $query->where('is_active', true)->where(fn ($q) => $q->whereNull('tenant_id')->when($tenantId, fn ($w) => $w->orWhere('tenant_id', $tenantId)));
    }

    public function getTypeOptions(): array
    {
        return self::$types;
    }

    public function getLegalStatusOptions(): array
    {
        return self::$legal;
    }

    public function getIsGlobalAttribute(): bool
    {
        return $this->tenant_id === null;
    }

    public function getIsReviewedAttribute(): bool
    {
        return $this->legal_status === 'reviewed';
    }

    public function getScopeNameAttribute(): string
    {
        return $this->tenant_id ? 'Propia' : 'Modelo base';
    }

    /** Los modelos base de la plataforma solo los edita el superadmin. */
    protected function assertWritable(): void
    {
        if ($this->tenant_id === null && \BackendAuth::getUser() && !\Aero\Realestate\Classes\CurrentTenant::isAdmin()) {
            throw new \ApplicationException('Los modelos base son de solo lectura: duplícalo para adaptarlo.');
        }
    }

    public function beforeDelete(): void
    {
        $this->assertWritable();
    }

    /** Registra la revisión de un abogado para esta versión exacta del texto. */
    public function markReviewed(string $by, ?string $notes = null): self
    {
        if (trim($by) === '') {
            throw new \Aero\Realestate\Classes\RealestateException('Indica quién revisó la plantilla (abogado o estudio).');
        }
        $this->forceFill(['legal_status' => 'reviewed', 'reviewed_by_name' => mb_substr(trim($by), 0, 255), 'reviewed_by' => \BackendAuth::getUser()?->id,
            'reviewed_at' => now(), 'legal_notes' => $notes])->saveQuietly();

        return $this->refresh();
    }

    /** Copia editable (propia del tenant) de una plantilla; arranca sin revisión legal. */
    public function duplicate(?int $tenantId = null): self
    {
        $copy = new self();
        $copy->tenant_id = $tenantId ?? $this->tenant_id;
        $copy->type = $this->type;
        $copy->name = $this->name . ' (copia)';
        $copy->body = $this->body;
        $copy->save();

        return $copy;
    }

    public function beforeSave(): void
    {
        $this->assertWritable();
        if ($this->isDirty('body')) {
            $this->body = \Html::clean($this->body);
        }
        if ($this->exists && $this->isDirty('body')) {
            $this->version = (int) $this->getOriginal('version') + 1;
            $this->legal_status = 'draft';
            $this->reviewed_at = $this->reviewed_by = $this->reviewed_by_name = null;
        }
    }
}
