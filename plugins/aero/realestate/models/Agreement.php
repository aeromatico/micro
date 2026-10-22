<?php namespace Aero\Realestate\Models;

use Model;

/**
 * Contrato generado desde una plantilla (arras, alquiler o anticrético). El estado y las fechas de vigencia los
 * maneja AgreementService; el texto solo se edita en borrador o en revisión.
 */
class Agreement extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public const EXPIRING_DAYS = 60;

    public $table = 'aero_realestate_agreements';

    public $fillable = [
        'tenant_id', 'type', 'template_id', 'title', 'property_id', 'offer_id', 'owner_id', 'client_id', 'agent_id', 'contract_id',
        'lead_id', 'body', 'variables', 'amount', 'total_price', 'currency', 'deposit', 'term_months', 'start_date', 'end_date',
    ];

    public $jsonable = ['variables'];

    public $rules = [
        'type'        => 'required|in:arras,rent,anticretico',
        'title'       => 'required|string|max:255',
        'property_id' => 'required|integer',
        'amount'      => 'required|numeric|min:0',
        'currency'    => 'required|in:BOB,USD',
    ];

    public $attributes = ['status' => 'draft', 'currency' => 'USD', 'amount' => 0];

    public $dates = ['start_date', 'end_date', 'signed_on'];

    public $belongsTo = [
        'template' => [AgreementTemplate::class, 'key' => 'template_id'],
        'property' => [Property::class, 'key' => 'property_id'],
        'offer'    => [Offer::class, 'key' => 'offer_id'],
        'owner'    => [Owner::class, 'key' => 'owner_id'],
        'client'   => [Client::class, 'key' => 'client_id'],
        'agent'    => [Agent::class, 'key' => 'agent_id'],
        'contract' => [Contract::class, 'key' => 'contract_id'],
    ];

    public $hasMany = [
        'events'    => [AgreementEvent::class, 'key' => 'agreement_id'],
        'documents' => [Document::class, 'key' => 'agreement_id'],
    ];

    public $attachOne = ['signed_file' => [\System\Models\File::class, 'public' => false]];

    public static array $statuses = [
        'draft' => 'Borrador', 'in_review' => 'En revisión', 'approved' => 'Aprobado', 'signed' => 'Firmado', 'finished' => 'Finalizado', 'cancelled' => 'Cancelado',
    ];

    /** Mientras estén así, el texto y los montos se pueden editar. */
    public const EDITABLE = ['draft', 'in_review'];

    public function getTypeOptions(): array
    {
        return AgreementTemplate::$types;
    }

    public function getStatusOptions(): array
    {
        return self::$statuses;
    }

    public function getCurrencyOptions(): array
    {
        return ['USD' => 'USD (dólares)', 'BOB' => 'BOB (bolivianos)'];
    }

    public function getTemplateIdOptions(): array
    {
        $tenant = \Aero\Realestate\Classes\CurrentTenant::isAdmin() ? $this->tenant_id : \Aero\Realestate\Classes\CurrentTenant::id();

        return AgreementTemplate::available($tenant)->when($this->type, fn ($q) => $q->where('type', $this->type))->orderBy('name')
            ->get()->mapWithKeys(fn ($t) => [$t->id => $t->name . ($t->is_reviewed ? ' ✔ revisada' : ' (sin revisión legal)')])->all();
    }

    public function getPropertyIdOptions(): array
    {
        return Property::visible()->orderBy('title')->pluck('title', 'id')->all();
    }

    public function getOfferIdOptions(): array
    {
        return Offer::visible()->when($this->property_id, fn ($q) => $q->where('property_id', $this->property_id))->with('property')->orderByDesc('id')->get()
            ->mapWithKeys(fn ($o) => [$o->id => ($o->property?->title ?? '') . ' · ' . $o->modality_name . ' ' . number_format((float) $o->amount, 0, ',', '.') . ' ' . $o->currency])->all();
    }

    public function getOwnerIdOptions(): array
    {
        return Owner::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getClientIdOptions(): array
    {
        return Client::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getAgentIdOptions(): array
    {
        return Agent::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getContractIdOptions(): array
    {
        return Contract::visible()->when($this->property_id, fn ($q) => $q->where('property_id', $this->property_id))->orderByDesc('id')->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->code . ' · ' . $c->status_name])->all();
    }

    public function getStatusNameAttribute(): string
    {
        return self::$statuses[$this->status] ?? (string) $this->status;
    }

    public function getTypeNameAttribute(): string
    {
        return AgreementTemplate::$types[$this->type] ?? (string) $this->type;
    }

    public function getIsEditableAttribute(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    /** Un contrato sin la revisión legal de su plantilla es un borrador aunque esté firmado en papel: el PDF lo advierte. */
    public function getIsLegallyReviewedAttribute(): bool
    {
        return (bool) $this->template_reviewed;
    }

    public function getDaysLeftAttribute(): ?int
    {
        return $this->end_date ? (int) now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false) : null;
    }

    public function scopeExpiring($query, int $days = self::EXPIRING_DAYS)
    {
        return $query->where('type', 'rent')->where('status', 'signed')->whereNotNull('end_date')->whereDate('end_date', '<=', now()->addDays($days)->toDateString());
    }

    public function beforeValidate(): void
    {
        if ($this->property_id && ($tenant = Property::whereKey($this->property_id)->value('tenant_id'))) {
            $this->tenant_id = $tenant;
        }
    }

    public function beforeSave(): void
    {
        $this->assertReferencesVisible([
            'property_id' => Property::class, 'offer_id' => Offer::class, 'owner_id' => Owner::class, 'client_id' => Client::class,
            'agent_id' => Agent::class, 'contract_id' => Contract::class,
        ]);

        if ($this->exists && !$this->internal && !in_array($this->getOriginal('status'), self::EDITABLE, true)) {
            foreach (['body', 'amount', 'currency', 'property_id', 'owner_id', 'client_id', 'type', 'template_id'] as $field) {
                if ($this->isDirty($field)) {
                    throw new \ApplicationException('El contrato ya fue aprobado: para cambiarlo reábrelo primero (vuelve a borrador).');
                }
            }
        }

        if ($this->body !== null && $this->isDirty('body')) {
            $this->body = \Html::clean($this->body); // el texto lo edita el usuario: sin scripts ni atributos peligrosos
        }
        if (!$this->owner_id && $this->property_id) {
            $this->owner_id = Property::whereKey($this->property_id)->value('owner_id');
        }
        if (!$this->agent_id && $this->property_id) {
            $this->agent_id = Property::whereKey($this->property_id)->value('agent_id');
        }
        if ($this->start_date && $this->term_months && !$this->isDirty('end_date') && ($this->isDirty('start_date') || $this->isDirty('term_months') || !$this->end_date)) {
            $this->end_date = $this->start_date->copy()->addMonthsNoOverflow((int) $this->term_months);
        }
    }

    public bool $internal = false;

    public function afterCreate(): void
    {
        static::whereKey($this->id)->update(['code' => $code = 'CTR-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT)]);
        $this->forceFill(['code' => $code])->syncOriginal();
    }
}
