<?php namespace Aero\Realestate\Models;

use Model;

/**
 * Contrato de anticrético. A diferencia del alquiler, el anticresista ENTREGA un capital
 * al propietario, que debe restituirlo al vencer. La entrega y la devolución viven en
 * Payment; el estado, en AnticreticoService (no se cambia a mano, salvo borrador).
 */
class Contract extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public const EXPIRING_DAYS = 60;

    public $table = 'aero_realestate_contracts';

    /** Lo que el servicio escribe sin pasar por el formulario. */
    public $fillable = [
        'tenant_id', 'property_id', 'offer_id', 'owner_id', 'client_id', 'agent_id', 'capital', 'currency', 'term_months',
        'start_date', 'end_date', 'conditions', 'return_conditions', 'notes',
    ];

    public $rules = [
        'property_id' => 'required|integer',
        'client_id'   => 'required|integer',
        'capital'     => 'required|numeric|min:0.01',
        'currency'    => 'required|in:BOB,USD',
        'term_months' => 'required|integer|min:1|max:600',
        'start_date'  => 'nullable|date',
    ];

    public $attributes = ['status' => 'draft', 'currency' => 'USD', 'return_status' => 'pending'];

    public $dates = ['start_date', 'end_date', 'activated_at', 'finished_at', 'docs_alert_at'];

    public $belongsTo = [
        'property' => [Property::class, 'key' => 'property_id'],
        'offer'    => [Offer::class, 'key' => 'offer_id'],
        'owner'    => [Owner::class, 'key' => 'owner_id'],
        'client'   => [Client::class, 'key' => 'client_id'],
        'agent'    => [Agent::class, 'key' => 'agent_id'],
    ];

    public $hasMany = [
        'payments'  => [Payment::class, 'key' => 'contract_id'],
        'documents' => [ContractDocument::class, 'key' => 'contract_id'],
        'events'    => [ContractEvent::class, 'key' => 'contract_id'],
    ];

    public static array $statuses = [
        'draft' => 'Borrador', 'reserved' => 'Reservado', 'active' => 'Vigente', 'finished' => 'Finalizado', 'cancelled' => 'Cancelado',
    ];

    public static array $returnStatuses = [
        'pending' => 'Pendiente', 'partial' => 'Parcial', 'completed' => 'Completada', 'dispute' => 'En disputa',
    ];

    /** Campos que definen "quién y qué" — no se tocan una vez firmado. */
    protected const IDENTITY = ['property_id', 'offer_id', 'client_id', 'owner_id'];

    /** Campos cuyo cambio queda en la bitácora. */
    protected const AUDITED = ['capital', 'currency', 'term_months', 'start_date', 'end_date', 'conditions', 'return_conditions', 'agent_id'];

    public bool $internal = false;

    protected array $auditChanges = [];

    public function getStatusOptions(): array
    {
        return self::$statuses;
    }

    public function getReturnStatusOptions(): array
    {
        return self::$returnStatuses;
    }

    public function getCurrencyOptions(): array
    {
        return ['USD' => 'USD (dólares)', 'BOB' => 'BOB (bolivianos)'];
    }

    public function getPropertyIdOptions(): array
    {
        return Property::visible()->orderBy('title')->pluck('title', 'id')->all();
    }

    public function getOfferIdOptions(): array
    {
        return Offer::visible()->where('modality', 'anticretico')->with('property')->orderByDesc('id')->get()
            ->mapWithKeys(fn ($o) => [$o->id => ($o->property?->title ?? '#' . $o->property_id) . ' — ' . number_format((float) $o->amount, 0, ',', '.') . ' ' . $o->currency])->all();
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Vigentes que vencen dentro de $days días (incluye los ya vencidos si $withOverdue). */
    public function scopeExpiring($query, int $days = self::EXPIRING_DAYS, bool $withOverdue = true)
    {
        $query->where('status', 'active')->whereNotNull('end_date')->whereDate('end_date', '<=', now()->addDays($days)->toDateString());

        return $withOverdue ? $query : $query->whereDate('end_date', '>=', now()->toDateString());
    }

    public function getStatusNameAttribute(): string
    {
        return self::$statuses[$this->status] ?? (string) $this->status;
    }

    public function getReturnStatusNameAttribute(): string
    {
        return self::$returnStatuses[$this->return_status] ?? (string) $this->return_status;
    }

    /** Días que faltan para el vencimiento (negativo = vencido). null si no tiene fecha. */
    public function getDaysLeftAttribute(): ?int
    {
        return $this->end_date ? (int) now()->startOfDay()->diffInDays($this->end_date->copy()->startOfDay(), false) : null;
    }

    /** Etiqueta visible: Vigente, Por vencer, Vencido… */
    public function getStatusLabelAttribute(): string
    {
        if ($this->status === 'active' && $this->days_left !== null) {
            if ($this->days_left < 0) {
                return 'Vencido';
            }
            if ($this->days_left <= self::EXPIRING_DAYS) {
                return 'Próximo a vencer';
            }
        }

        return $this->status_name;
    }

    public function getPendingAmountAttribute(): float
    {
        return max(0.0, round((float) $this->delivered_amount - (float) $this->returned_amount, 2));
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
            'property_id' => Property::class, 'offer_id' => Offer::class, 'owner_id' => Owner::class,
            'client_id' => Client::class, 'agent_id' => Agent::class,
        ]);

        if ($this->offer_id && Offer::whereKey($this->offer_id)->where('property_id', $this->property_id)->where('modality', 'anticretico')->doesntExist()) {
            throw new \ApplicationException('La oferta elegida no es un anticrético de esa propiedad.');
        }

        if ($this->exists && !$this->internal && in_array($this->getOriginal('status'), ['active', 'finished', 'cancelled'], true)) {
            foreach (self::IDENTITY as $field) {
                if ($this->isDirty($field)) {
                    throw new \ApplicationException('Después de la firma no se cambia la propiedad, la oferta ni las partes del contrato.');
                }
            }
        }

        if (!$this->owner_id && $this->property_id) {
            $this->owner_id = Property::whereKey($this->property_id)->value('owner_id');
        }
        if (!$this->agent_id && $this->property_id) {
            $this->agent_id = Property::whereKey($this->property_id)->value('agent_id');
        }

        // El vencimiento sigue al inicio y al plazo, salvo que alguien lo fije a mano.
        if ($this->start_date && $this->term_months && !$this->isDirty('end_date') && ($this->isDirty('start_date') || $this->isDirty('term_months') || !$this->end_date)) {
            $this->end_date = $this->start_date->copy()->addMonthsNoOverflow((int) $this->term_months);
        }

        $this->auditChanges = [];
        if ($this->exists) {
            foreach (self::AUDITED as $field) {
                if ($this->isDirty($field)) {
                    $this->auditChanges[$field] = [$this->getOriginal($field), $this->getAttribute($field)];
                }
            }
        }
    }

    public function afterCreate(): void
    {
        static::whereKey($this->id)->update(['code' => $code = 'ANT-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT)]);
        $this->forceFill(['code' => $code])->syncOriginal();
        ContractEvent::log($this, 'created', 'Contrato creado en borrador.');
    }

    public function afterUpdate(): void
    {
        // Los cambios que hace el servicio (renovar, activar…) ya se registran con su propio texto.
        if ($this->auditChanges && !$this->internal) {
            ContractEvent::log($this, 'modified', 'Se modificaron: ' . implode(', ', array_keys($this->auditChanges)) . '.', $this->auditChanges);
        }
        $this->auditChanges = [];
    }
}
