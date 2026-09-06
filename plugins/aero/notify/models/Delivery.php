<?php namespace Aero\Notify\Models;

use Model;

/**
 * Registro de cada intento de entrega individual (un destinatario, un canal).
 * `context` guarda las variables con las que se disparó el evento, para poder
 * reconstruir el mensaje exacto en un reenvío sin depender de que la
 * plantilla no haya cambiado desde entonces.
 */
class Delivery extends Model
{
    public $table = 'aero_notify_deliveries';

    public $timestamps = true;

    protected $guarded = [];

    protected $jsonable = ['context'];

    protected $dates = ['sent_at'];

    protected $casts = [
        'tenant_id' => 'integer',
    ];

    public $belongsTo = [
        'event'    => [Event::class],
        'rule'     => [Rule::class],
        'template' => [Template::class],
        'tenant'   => [\Aero\Sites\Models\Tenant::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function markSent(string $externalId = ''): void
    {
        $this->status      = 'sent';
        $this->external_id = $externalId ?: null;
        $this->sent_at      = now();
        $this->error        = null;
        $this->save();
    }

    public function markFailed(string $error): void
    {
        $this->status = 'failed';
        $this->error   = $error;
        $this->save();
    }

    public function markSkipped(string $reason): void
    {
        $this->status = 'skipped';
        $this->error   = $reason;
        $this->save();
    }
}
