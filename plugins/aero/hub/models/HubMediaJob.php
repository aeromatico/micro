<?php namespace Aero\Hub\Models;

use Model;

/**
 * Mapea un jobId asíncrono de YepAPI (`/v1/media/queue`) al hold de créditos
 * abierto al encolarlo, para poder liquidarlo (settle/refund) cuando
 * `/v1/media/status/{jobId}` reporte el resultado final — ver
 * `Aero\Hub\Classes\HubCredits::settleMediaJob()`/`refundMediaJob()`.
 */
class HubMediaJob extends Model
{
    public $table = 'aero_hub_media_jobs';

    public $fillable = [
        'job_id', 'tenant_id', 'endpoint_code', 'credit_transaction_id', 'status',
    ];

    public const QUEUED = 'queued';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';

    public function scopeUnresolved($query)
    {
        return $query->where('status', self::QUEUED);
    }
}
