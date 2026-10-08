<?php namespace Aero\Tracking\Models;

use Model;

/** Hecho clave detectado al seguir un feed (cambio de fase, ETA, mensaje...). */
class FeedEvent extends Model
{
    public $table = 'aero_tracking_feed_events';

    public $timestamps = false;

    public $fillable = ['feed_id', 'tenant_id', 'type', 'data', 'occurred_at', 'created_at'];

    public $jsonable = ['data'];

    protected $casts = ['occurred_at' => 'datetime', 'created_at' => 'datetime'];

    public $belongsTo = ['feed' => Feed::class];

    public const TYPES = [
        'started'       => 'Seguimiento iniciado',
        'phase_changed' => 'Cambió el estado',
        'eta_changed'   => 'Cambió la hora estimada',
        'delay_changed' => 'Cambió el aviso de demora',
        'message_changed' => 'Nuevo mensaje',
        'finished'      => 'Pedido finalizado',
        'expired'       => 'El enlace dejó de responder',
        'failed'        => 'Seguimiento detenido por errores',
        'stopped'       => 'Detenido por el usuario',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? (string) $this->type;
    }
}
