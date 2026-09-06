<?php namespace Aero\Connector\Models;

use Model;

/**
 * Bitácora unificada de request/response: llamadas salientes (incluidas las
 * de prueba del tester) y deliveries de webhooks entrantes.
 */
class ConnectorLog extends Model
{
    public $table = 'aero_connector_logs';

    public $timestamps = false;

    public $fillable = [
        'direction', 'connector_id', 'webhook_endpoint_id',
        'request_payload', 'response_payload', 'status_code',
        'duration_ms', 'is_test', 'created_at',
    ];

    public $jsonable = ['request_payload', 'response_payload'];

    protected $dates = ['created_at'];

    protected $casts = [
        'is_test' => 'boolean',
    ];

    public $attributes = [
        'created_at' => null,
    ];

    public $belongsTo = [
        'connector'       => \Aero\Connector\Models\Connector::class,
        'webhookEndpoint' => [\Aero\Connector\Models\WebhookEndpoint::class, 'key' => 'webhook_endpoint_id'],
    ];

    public function beforeCreate()
    {
        $this->created_at = $this->created_at ?: now();
    }

    public function getDirectionLabelAttribute(): string
    {
        return $this->direction === 'in' ? 'Entrante' : 'Saliente';
    }
}
