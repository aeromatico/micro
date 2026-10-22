<?php namespace Aero\Realestate\Models;

use Model;

/**
 * Oportunidad comercial: un cliente interesado, normalmente en una propiedad y modalidad concretas.
 * La etapa solo se mueve por LeadService / DealService (queda en el historial).
 */
class Lead extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_leads';

    public $fillable = ['tenant_id', 'client_id', 'property_id', 'offer_id', 'agent_id', 'modality', 'source', 'notes', 'next_follow_up_at'];

    public $rules = [
        'client_id' => 'required|integer',
        'modality'  => 'nullable|in:sale,rent,anticretico',
    ];

    public $attributes = ['stage' => 'new'];

    public $dates = ['last_contact_at', 'next_follow_up_at', 'follow_up_alerted_at', 'closed_at'];

    public $belongsTo = [
        'client'   => [Client::class, 'key' => 'client_id'],
        'property' => [Property::class, 'key' => 'property_id'],
        'offer'    => [Offer::class, 'key' => 'offer_id'],
        'agent'    => [Agent::class, 'key' => 'agent_id'],
    ];

    public $hasMany = [
        'interactions' => [Interaction::class, 'key' => 'lead_id'],
        'visits'       => [Visit::class, 'key' => 'lead_id'],
    ];

    public static array $stages = [
        'new' => 'Nuevo contacto', 'contacted' => 'Contactado', 'visit_scheduled' => 'Visita programada',
        'negotiation' => 'Negociación', 'closed_won' => 'Operación cerrada', 'closed_lost' => 'Perdido',
    ];

    public const OPEN = ['new', 'contacted', 'visit_scheduled', 'negotiation'];

    public function scopeOpen($query)
    {
        return $query->whereIn('stage', self::OPEN);
    }

    public function getStageOptions(): array
    {
        return self::$stages;
    }

    public function getModalityOptions(): array
    {
        return Offer::$modalities;
    }

    public function getSourceOptions(): array
    {
        return ['portal' => 'Portal', 'whatsapp' => 'WhatsApp', 'referido' => 'Referido', 'redes' => 'Redes sociales', 'visita' => 'Visita directa', 'otro' => 'Otro'];
    }

    public function getClientIdOptions(): array
    {
        return Client::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getPropertyIdOptions(): array
    {
        return Property::visible()->orderBy('title')->pluck('title', 'id')->all();
    }

    public function getOfferIdOptions(): array
    {
        return Offer::visible()->when($this->property_id, fn ($q) => $q->where('property_id', $this->property_id))->with('property')->orderByDesc('id')->get()
            ->mapWithKeys(fn ($o) => [$o->id => ($o->property?->title ?? '#' . $o->property_id) . ' · ' . $o->modality_name . ' ' . number_format((float) $o->amount, 0, ',', '.') . ' ' . $o->currency])->all();
    }

    public function getAgentIdOptions(): array
    {
        return Agent::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getStageNameAttribute(): string
    {
        return self::$stages[$this->stage] ?? (string) $this->stage;
    }

    public function getIsOpenAttribute(): bool
    {
        return in_array($this->stage, self::OPEN, true);
    }

    public function getIsFollowUpDueAttribute(): bool
    {
        return $this->is_open && $this->next_follow_up_at && $this->next_follow_up_at->lte(now());
    }

    public function beforeValidate(): void
    {
        if ($this->client_id && ($tenant = Client::whereKey($this->client_id)->value('tenant_id'))) {
            $this->tenant_id = $tenant;
        }
    }

    public function beforeSave(): void
    {
        $this->assertReferencesVisible([
            'client_id' => Client::class, 'property_id' => Property::class, 'offer_id' => Offer::class, 'agent_id' => Agent::class,
        ]);

        if ($this->offer_id && ($offer = Offer::find($this->offer_id))) {
            $this->property_id = $this->property_id ?: $offer->property_id;
            $this->modality = $offer->modality;
        }
        if (!$this->agent_id) {
            $this->agent_id = $this->property_id ? Property::whereKey($this->property_id)->value('agent_id') : null;
            $this->agent_id = $this->agent_id ?: Client::whereKey($this->client_id)->value('agent_id');
        }
    }

    public function afterCreate(): void
    {
        Interaction::log($this->client_id, $this->id, $this->tenant_id, 'note', 'Oportunidad creada' . ($this->property_id ? ' para «' . Property::whereKey($this->property_id)->value('title') . '»' : '') . '.');
    }
}
