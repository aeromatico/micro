<?php namespace Aero\Tickets\Models;

use Aero\Tickets\Classes\Audit as AuditLog;
use Aero\Tickets\Classes\CommissionResolver;
use Model;

/** Condición comercial por organizador o por evento. Cada campo vacío hereda del siguiente nivel. */
class CommissionRule extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_tickets_commission_rules';
    public $fillable = ['scope', 'tenant_id', 'event_id', 'percent', 'fixed', 'payer', 'is_active', 'valid_from', 'valid_until', 'note'];
    public $dates = ['valid_from', 'valid_until'];

    public $rules = [
        'scope' => 'required|in:tenant,event', 'tenant_id' => 'required|integer',
        'percent' => 'nullable|numeric|min:0|max:50', 'fixed' => 'nullable|numeric|min:0|max:100000', 'payer' => 'nullable|in:buyer,organizer',
        'valid_until' => 'nullable|date|after_or_equal:valid_from',
    ];

    public $attributes = ['is_active' => true];

    public $belongsTo = ['event' => [Event::class, 'key' => 'event_id']];

    public function getScopeOptions(): array { return ['tenant' => 'Organizador (todos sus eventos)', 'event' => 'Un evento']; }
    public function getPayerOptions(): array { return ['buyer' => 'El comprador (se suma)', 'organizer' => 'El organizador (se descuenta)']; }
    public function getTenantIdOptions(): array
    {
        return class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all() : [];
    }
    public function getEventIdOptions(): array
    {
        return Event::orderByDesc('starts_at')->limit(300)->get()->mapWithKeys(fn ($e) => [$e->id => $e->name . ' (#' . $e->id . ')'])->all();
    }

    public function beforeSave(): void
    {
        foreach (['percent', 'fixed', 'payer', 'event_id', 'valid_from', 'valid_until'] as $f) {
            if ($this->{$f} === '') $this->{$f} = null;
        }
        if ($this->scope === 'event') {
            $ev = Event::withTrashed()->find($this->event_id);
            if (!$ev) throw new \ApplicationException('Elige el evento de la regla.');
            $this->tenant_id = $ev->tenant_id;                       // coherente con el evento
        } else {
            $this->event_id = null;
        }
        if ($this->percent === null && $this->fixed === null && $this->payer === null) {
            throw new \ApplicationException('La regla no cambia nada: define al menos el porcentaje, el monto fijo o quién paga.');
        }
        $dupe = static::where('scope', $this->scope)->where('tenant_id', $this->tenant_id)->where('is_active', true)
            ->when($this->event_id, fn ($q) => $q->where('event_id', $this->event_id), fn ($q) => $q->whereNull('event_id'))
            ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id));
        if ($this->is_active && $dupe->exists()) {
            throw new \ApplicationException('Ya hay una regla activa para este ' . ($this->scope === 'event' ? 'evento' : 'organizador') . '. Edítala o desactívala.');
        }
        if (!$this->exists) {
            $this->created_by = \BackendAuth::getUser()?->login;
        }
    }

    public function afterSave(): void
    {
        CommissionResolver::flush();
        AuditLog::record($this->wasRecentlyCreated ? 'commission_rule.created' : 'commission_rule.updated', 'commission_rule', $this->id, [
            'tenant_id' => $this->tenant_id, 'event_id' => $this->event_id, 'changes' => AuditLog::diff($this->getOriginal(), $this->getAttributes(), ['percent', 'fixed', 'payer', 'is_active', 'valid_from', 'valid_until']),
        ]);
    }

    public function afterDelete(): void
    {
        CommissionResolver::flush();
        AuditLog::record('commission_rule.deleted', 'commission_rule', $this->id, ['tenant_id' => $this->tenant_id, 'event_id' => $this->event_id, 'note' => 'Regla eliminada']);
    }

    /** ¿Aplica hoy? */
    public function appliesNow(): bool
    {
        return $this->is_active && (!$this->valid_from || $this->valid_from->lte(today())) && (!$this->valid_until || $this->valid_until->gte(today()));
    }
}
