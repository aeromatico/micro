<?php namespace Aero\Tracking\Models;

use Model;
use Illuminate\Support\Str;

/** Lo que se rastrea: una persona, un vehículo, cualquier cosa con GPS. */
class Asset extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_tracking_assets';

    public $fillable = ['tenant_id', 'name', 'type', 'code', 'is_active', 'meta'];

    public $jsonable = ['meta'];

    public $rules = [
        'name' => 'required|max:191',
        'type' => 'required|max:40',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public $hasMany = [
        'positions' => Position::class,
        'jobs'      => Job::class,
    ];

    /** Sin posición en 5 minutos el activo se considera desconectado. */
    public const ONLINE_WINDOW_MINUTES = 5;

    public function beforeCreate(): void
    {
        $this->ingest_token = $this->ingest_token ?: Str::random(40);
    }

    public function getTenantIdOptions(): array
    {
        return class_exists(\Aero\Sites\Models\Tenant::class)
            ? \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all()
            : [];
    }

    public function regenerateToken(): void
    {
        $this->ingest_token = Str::random(40);
        $this->save();
    }

    public function getIngestUrlAttribute(): ?string
    {
        return $this->ingest_token ? url('api/v1/tracking/ingest/' . $this->ingest_token) : null;
    }

    public function getIsOnlineAttribute(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gt(now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
    }

    public function getStatusLabelAttribute(): string
    {
        return !$this->is_active ? 'Inactivo' : ($this->is_online ? 'En línea' : 'Sin señal');
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function present(bool $withToken = false): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->name,
            'type'      => $this->type,
            'code'      => $this->code,
            'is_active' => $this->is_active,
            'online'    => $this->is_online,
            'meta'      => $this->meta,
            'position'  => $this->last_lat === null ? null : [
                'lat'      => (float) $this->last_lat,
                'lng'      => (float) $this->last_lng,
                'speed'    => $this->last_speed,
                'heading'  => $this->last_heading,
                'battery'  => $this->last_battery,
                'recorded_at' => $this->last_seen_at?->toIso8601String(),
            ],
        ] + ($withToken ? ['ingest_url' => $this->ingest_url] : []);
    }
}
