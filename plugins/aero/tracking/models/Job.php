<?php namespace Aero\Tracking\Models;

use Model;
use Illuminate\Support\Str;

/**
 * Una unidad de trabajo que se mueve entre puntos. No sabe qué es (pedido,
 * carga, traslado): external_type/external_id enlazan con quien lo creó.
 */
class Job extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Purgeable;

    public $table = 'aero_tracking_jobs';

    public $fillable = [
        'tenant_id', 'asset_id', 'reference', 'external_type', 'external_id',
        'title', 'notes', 'status', 'meta', 'scheduled_at',
    ];

    public $jsonable = ['meta'];

    /** Campo virtual del formulario: se sincroniza con Stop en afterSave. */
    public $purgeable = ['stops_input'];

    public $rules = [
        'status' => 'required',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public $belongsTo = ['asset' => Asset::class];

    public $hasMany = ['stops' => [Stop::class, 'order' => 'sequence']];

    public const STATUSES = [
        'pending'     => 'Pendiente',
        'assigned'    => 'Asignado',
        'in_progress' => 'En curso',
        'completed'   => 'Completado',
        'cancelled'   => 'Cancelado',
        'failed'      => 'Fallido',
    ];

    public const OPEN = ['pending', 'assigned', 'in_progress'];

    public function getStatusOptions(): array
    {
        return self::STATUSES;
    }

    public function getAssetIdOptions(): array
    {
        $query = Asset::where('is_active', true)->orderBy('name');

        if (!\Aero\Tracking\Classes\CurrentTenant::isAdmin()) {
            $query->where('tenant_id', \Aero\Tracking\Classes\CurrentTenant::id());
        }

        return $query->pluck('name', 'id')->all();
    }

    public function getTenantIdOptions(): array
    {
        return class_exists(\Aero\Sites\Models\Tenant::class)
            ? \Aero\Sites\Models\Tenant::orderBy('name')->pluck('name', 'id')->all()
            : [];
    }

    public function getStopsInputAttribute(): array
    {
        return $this->exists
            ? $this->stops->map(fn ($s) => $s->only(['type', 'name', 'address', 'lat', 'lng', 'contact_name', 'contact_phone', 'notes']))->all()
            : [];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function beforeCreate(): void
    {
        $this->uuid = $this->uuid ?: (string) Str::uuid();
    }

    /** Paradas escritas desde el panel: se reemplazan solo si el formulario las trajo. */
    public function afterSave(): void
    {
        $rows = $this->getOriginalPurgeValue('stops_input');

        if (!is_array($rows)) {
            return;
        }

        $this->stops()->delete();

        foreach (array_values($rows) as $i => $row) {
            $this->stops()->create([
                'tenant_id'     => $this->tenant_id,
                'sequence'      => $i + 1,
                'type'          => $row['type'] ?? 'delivery',
                'name'          => $row['name'] ?? null,
                'address'       => $row['address'] ?? null,
                'lat'           => ($row['lat'] ?? '') !== '' ? $row['lat'] : null,
                'lng'           => ($row['lng'] ?? '') !== '' ? $row['lng'] : null,
                'contact_name'  => $row['contact_name'] ?? null,
                'contact_phone' => $row['contact_phone'] ?? null,
                'notes'         => $row['notes'] ?? null,
            ]);
        }
    }

    public function present(bool $withStops = true): array
    {
        return [
            'id'            => $this->uuid,
            'reference'     => $this->reference,
            'external_type' => $this->external_type,
            'external_id'   => $this->external_id,
            'title'         => $this->title,
            'notes'         => $this->notes,
            'status'        => $this->status,
            'asset_id'      => $this->asset_id,
            'meta'          => $this->meta,
            'scheduled_at'  => $this->scheduled_at?->toIso8601String(),
            'started_at'    => $this->started_at?->toIso8601String(),
            'completed_at'  => $this->completed_at?->toIso8601String(),
            'created_at'    => $this->created_at?->toIso8601String(),
        ] + ($withStops ? ['stops' => $this->stops->map->present()->all()] : []);
    }
}
