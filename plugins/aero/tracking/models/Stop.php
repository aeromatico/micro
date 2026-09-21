<?php namespace Aero\Tracking\Models;

use Model;

class Stop extends Model
{
    public $table = 'aero_tracking_stops';

    public $fillable = [
        'tenant_id', 'sequence', 'type', 'name', 'address', 'lat', 'lng',
        'contact_name', 'contact_phone', 'window_start', 'window_end',
        'service_minutes', 'status', 'notes',
    ];

    public $jsonable = ['proof'];

    protected $casts = [
        'window_start' => 'datetime',
        'window_end'   => 'datetime',
        'arrived_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public $belongsTo = ['job' => Job::class];

    public const TYPES = ['pickup' => 'Recojo', 'delivery' => 'Entrega', 'service' => 'Servicio'];
    public const STATUSES = ['pending', 'arrived', 'done', 'skipped', 'failed'];

    public function present(): array
    {
        return [
            'id'              => $this->id,
            'sequence'        => $this->sequence,
            'type'            => $this->type,
            'name'            => $this->name,
            'address'         => $this->address,
            'lat'             => $this->lat === null ? null : (float) $this->lat,
            'lng'             => $this->lng === null ? null : (float) $this->lng,
            'contact_name'    => $this->contact_name,
            'contact_phone'   => $this->contact_phone,
            'window_start'    => $this->window_start?->toIso8601String(),
            'window_end'      => $this->window_end?->toIso8601String(),
            'service_minutes' => $this->service_minutes,
            'status'          => $this->status,
            'arrived_at'      => $this->arrived_at?->toIso8601String(),
            'completed_at'    => $this->completed_at?->toIso8601String(),
            'proof'           => $this->proof,
            'notes'           => $this->notes,
        ];
    }
}
