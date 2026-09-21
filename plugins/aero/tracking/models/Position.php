<?php namespace Aero\Tracking\Models;

use Model;

class Position extends Model
{
    public $table = 'aero_tracking_positions';

    public $timestamps = false;

    public $fillable = [
        'asset_id', 'tenant_id', 'lat', 'lng', 'speed', 'heading',
        'accuracy', 'altitude', 'battery', 'recorded_at', 'created_at',
    ];

    protected $casts = ['recorded_at' => 'datetime', 'created_at' => 'datetime'];

    public $belongsTo = ['asset' => Asset::class];

    public function present(): array
    {
        return [
            'lat'         => (float) $this->lat,
            'lng'         => (float) $this->lng,
            'speed'       => $this->speed,
            'heading'     => $this->heading,
            'accuracy'    => $this->accuracy,
            'battery'     => $this->battery,
            'recorded_at' => $this->recorded_at->toIso8601String(),
        ];
    }
}
