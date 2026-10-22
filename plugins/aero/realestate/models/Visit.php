<?php namespace Aero\Realestate\Models;

use Model;

/** Visita a una propiedad. El estado y los avisos los maneja VisitService. */
class Visit extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_visits';

    public $fillable = ['tenant_id', 'property_id', 'client_id', 'lead_id', 'agent_id', 'starts_at', 'ends_at', 'notes', 'source'];

    public $rules = [
        'property_id' => 'required|integer',
        'client_id'   => 'required|integer',
        'starts_at'   => 'required|date',
    ];

    public $attributes = ['status' => 'requested', 'source' => 'panel'];

    public $dates = ['starts_at', 'ends_at', 'reminder_sent_at'];

    public $belongsTo = [
        'property' => [Property::class, 'key' => 'property_id'],
        'client'   => [Client::class, 'key' => 'client_id'],
        'lead'     => [Lead::class, 'key' => 'lead_id'],
        'agent'    => [Agent::class, 'key' => 'agent_id'],
    ];

    public static array $statuses = [
        'requested' => 'Solicitada', 'confirmed' => 'Confirmada', 'rescheduled' => 'Reprogramada', 'done' => 'Realizada', 'cancelled' => 'Cancelada',
    ];

    /** Las que ocupan la agenda del agente. */
    public const ACTIVE = ['requested', 'confirmed', 'rescheduled'];

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE);
    }

    public function scopeUpcoming($query)
    {
        return $query->active()->where('starts_at', '>=', now());
    }

    public function getStatusOptions(): array
    {
        return self::$statuses;
    }

    public function getPropertyIdOptions(): array
    {
        return Property::visible()->orderBy('title')->pluck('title', 'id')->all();
    }

    public function getClientIdOptions(): array
    {
        return Client::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getAgentIdOptions(): array
    {
        return Agent::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getLeadIdOptions(): array
    {
        return Lead::visible()->open()->when($this->client_id, fn ($q) => $q->where('client_id', $this->client_id))->with('client')->orderByDesc('id')->limit(200)->get()
            ->mapWithKeys(fn ($l) => [$l->id => '#' . $l->id . ' ' . ($l->client?->name ?? '') . ' · ' . $l->stage_name])->all();
    }

    public function getStatusNameAttribute(): string
    {
        return self::$statuses[$this->status] ?? (string) $this->status;
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
            'property_id' => Property::class, 'client_id' => Client::class, 'lead_id' => Lead::class, 'agent_id' => Agent::class,
        ]);

        if (!$this->agent_id && $this->property_id) {
            $this->agent_id = Property::whereKey($this->property_id)->value('agent_id');
        }
        if ($this->starts_at && (!$this->ends_at || $this->isDirty('starts_at') && !$this->isDirty('ends_at'))) {
            $minutes = RealestateSettings::forTenant($this->tenant_id)->visit_minutes;
            $this->ends_at = $this->starts_at->copy()->addMinutes($minutes);
        }
    }

    public function afterCreate(): void
    {
        static::whereKey($this->id)->update(['code' => $code = 'VIS-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT)]);
        $this->forceFill(['code' => $code])->syncOriginal();
    }
}
