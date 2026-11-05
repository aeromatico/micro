<?php namespace Aero\Tickets\Models;

use Model;

/** Una lectura en puerta (válida o no). Solo se agrega: es la bitácora de accesos. */
class Scan extends Model
{
    use \Aero\Tickets\Classes\TenantScoped;

    public $table = 'aero_tickets_scans';
    public $timestamps = false;
    public $guarded = [];
    public $dates = ['scanned_at'];

    public $belongsTo = ['event' => [Event::class, 'key' => 'event_id']];

    public static array $results = [
        'ok' => 'Ingreso permitido', 'already_used' => 'Ya utilizada', 'invalid' => 'QR/código inválido', 'wrong_event' => 'De otro evento',
        'cancelled' => 'Anulada', 'expired' => 'Vencida', 'not_issued' => 'No emitida (pendiente)', 'event_cancelled' => 'Evento cancelado',
        'event_not_open' => 'Evento sin abrir', 'not_allowed' => 'Operador sin permiso',
    ];
    public static array $methods = ['qr' => 'QR', 'code' => 'Código', 'manual' => 'Manual'];

    public function getResultOptions(): array { return self::$results; }
    public function getMethodOptions(): array { return self::$methods; }
    public function getResultLabelAttribute(): string { return self::$results[$this->result] ?? $this->result; }

    public function getEventIdOptions(): array
    {
        return Event::visible()->orderByDesc('starts_at')->pluck('name', 'id')->all();
    }

    public function beforeCreate(): void
    {
        $this->scanned_at = $this->scanned_at ?: now();
    }

    public function beforeUpdate(): void
    {
        throw new \ApplicationException('El registro de accesos no se modifica.');
    }

    public function beforeDelete(): void
    {
        throw new \ApplicationException('El registro de accesos no se borra.');
    }
}
