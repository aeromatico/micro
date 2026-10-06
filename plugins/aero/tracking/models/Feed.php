<?php namespace Aero\Tracking\Models;

use Aero\Tracking\Classes\Feeds\FeedRegistry;
use Model;

/**
 * Un enlace público de rastreo de terceros que seguimos en nombre del usuario.
 * Se crea solo vía FeedService::add(); aquí no hay lógica de proveedor.
 */
class Feed extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_tracking_feeds';

    public $fillable = ['label'];

    public $rules = ['label' => 'nullable|max:191'];

    public $jsonable = ['state'];

    protected $casts = [
        'next_poll_at'   => 'datetime',
        'last_polled_at' => 'datetime',
        'finished_at'    => 'datetime',
    ];

    public $belongsTo = ['job' => Job::class, 'asset' => Asset::class];

    public $hasMany = ['events' => [FeedEvent::class, 'order' => 'occurred_at desc']];

    public const STATUSES = [
        'active'    => 'Siguiendo',
        'completed' => 'Entregado',
        'cancelled' => 'Cancelado',
        'expired'   => 'Enlace vencido',
        'failed'    => 'Falló',
        'stopped'   => 'Detenido',
    ];

    public const PHASES = [
        'queued'     => 'En cola',
        'confirmed'  => 'Confirmado',
        'at_origin'  => 'Repartidor en el comercio',
        'on_the_way' => 'En camino',
        'delivered'  => 'Entregado',
        'cancelled'  => 'Cancelado',
    ];

    public function getTenantIdOptions(): array
    {
        return class_exists(\Aero\Sites\Models\Tenant::class)
            ? \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all()
            : [];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? (string) $this->status;
    }

    public function getPhaseLabelAttribute(): string
    {
        return self::PHASES[$this->phase] ?? '—';
    }

    public function getProviderLabelAttribute(): string
    {
        return FeedRegistry::labels()[$this->provider] ?? (string) $this->provider;
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->label ?: (data_get($this->state, 'title') ?: ($this->provider_label . ' ' . $this->external_id));
    }

    public function getMapUrlAttribute(): ?string
    {
        $lat = data_get($this->state, 'lat');
        $lng = data_get($this->state, 'lng');

        return $lat && $lng ? "https://www.openstreetmap.org/?mlat={$lat}&mlon={$lng}#map=16/{$lat}/{$lng}" : null;
    }
}
