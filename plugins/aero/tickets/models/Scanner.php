<?php namespace Aero\Tickets\Models;

use Model;

/**
 * Persona o dispositivo autorizado a validar entradas SOLO de los eventos que se le asignan.
 * Entra con un enlace secreto (sin cuenta del panel); del enlace solo se guarda su hash.
 */
class Scanner extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Tickets\Classes\TenantScoped;

    public $table = 'aero_tickets_scanners';
    public $fillable = ['name', 'is_active'];
    public $rules = ['name' => 'required|string|max:255'];
    public $attributes = ['is_active' => true];

    public $belongsToMany = [
        'events' => [Event::class, 'table' => 'aero_tickets_scanner_events', 'key' => 'scanner_id', 'otherKey' => 'event_id'],
    ];
    public $dates = ['last_seen_at'];

    /** Enlace en claro: solo existe en memoria justo después de crear o regenerar. */
    public ?string $plainToken = null;

    public function beforeCreate(): void
    {
        // En el panel, el tenant lo fija el contexto (el superadmin puede elegirlo); un tenant nunca lo elige. Sin sesión (código/tests) se respeta.
        if (\BackendAuth::getUser()) {
            $this->tenant_id = (\Aero\Tickets\Classes\CurrentTenant::isAdmin() && $this->tenant_id) ? $this->tenant_id : \Aero\Tickets\Classes\CurrentTenant::id();
        }
        if (!$this->tenant_id) {
            throw new \ApplicationException('Falta el espacio de trabajo.');
        }
        $max = Settings::maxScannersPerTenant();
        if ($max > 0 && static::where('tenant_id', $this->tenant_id)->count() >= $max) {
            throw new \ApplicationException("Cada organizador puede tener hasta {$max} escáneres.");
        }
        $this->created_by = \BackendAuth::getUser()?->login;
        $this->issueToken();
    }

    /** Genera un secreto nuevo (el anterior deja de funcionar). */
    public function issueToken(): string
    {
        $this->plainToken = \Str::random(40);
        $this->token_hash = hash('sha256', $this->plainToken);
        $this->token_hint = substr($this->plainToken, -4);

        return $this->plainToken;
    }

    public static function findByToken(?string $token, ?int $tenantId = null): ?self
    {
        if (!$token || strlen($token) !== 40) {
            return null;
        }
        $q = static::where('token_hash', hash('sha256', $token))->where('is_active', true);
        if ($tenantId) {
            $q->where('tenant_id', $tenantId);
        }

        return $q->first();
    }

    /** Eventos que puede validar: asignados, del mismo tenant y todavía operables. */
    public function allowedEvents()
    {
        return $this->events()->where('aero_tickets_events.tenant_id', $this->tenant_id)->whereIn('status', ['published', 'ongoing'])->where('moderation', 'ok')->orderBy('starts_at')->get();
    }

    public function canScan(Event $event): bool
    {
        return $this->is_active && (int) $event->tenant_id === (int) $this->tenant_id
            && $this->events()->where('aero_tickets_events.id', $event->id)->exists();
    }

    public function getUrlAttribute(): ?string
    {
        return $this->plainToken ? 'https://' . $this->hostFor() . '/escaner/' . $this->plainToken : null;
    }

    protected function hostFor(): string
    {
        try {
            return \Aero\Sites\Models\Tenant::find($this->tenant_id)?->primary_domain ?: parse_url(url('/'), PHP_URL_HOST);
        } catch (\Throwable $e) {
            return (string) parse_url(url('/'), PHP_URL_HOST);
        }
    }

    public function getTenantIdOptions(): array
    {
        return class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all() : [];
    }

    public function getEventsOptions(): array
    {
        return Event::visible()->whereIn('status', ['draft', 'published', 'ongoing'])->orderByDesc('starts_at')->pluck('name', 'id')->all();
    }

    /** Un escáner solo puede apuntar a eventos de su propio tenant (ids manipulados en el POST). */
    public function beforeSave(): void
    {
        // la sincronización de eventos se valida en afterSave
    }

    public function afterSave(): void
    {
        $ids = $this->events()->pluck('aero_tickets_events.id')->all();
        $bad = Event::whereIn('id', $ids)->where('tenant_id', '!=', $this->tenant_id)->pluck('id')->all();
        if ($bad) {
            $this->events()->detach($bad);
        }
    }
}
