<?php namespace Aero\Sms\Models;

use Model;

class Message extends Model
{
    public $table = 'aero_sms_messages';

    public $fillable = [
        'uuid', 'batch_id', 'tenant_id', 'api_key_id', 'consumer', 'reference', 'to', 'body',
        'segments', 'encoding', 'status', 'scheduled_at',
    ];

    protected $dates = ['scheduled_at', 'sent_at', 'delivered_at', 'refunded_at'];

    public $belongsTo = [
        'batch' => [Batch::class],
    ];

    /** Estados de los que ya no se sale (excepto por webhook tardío de entrega). */
    public const FINAL = ['delivered', 'failed', 'undelivered', 'blocked', 'cancelled'];

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL, true);
    }

    public function getTenantNameAttribute(): string
    {
        if (!$this->tenant_id) {
            return 'Plataforma';
        }

        if (class_exists(\Aero\Sites\Models\Tenant::class)) {
            return \Aero\Sites\Models\Tenant::find($this->tenant_id)?->name ?? "Tenant #{$this->tenant_id}";
        }

        return "Tenant #{$this->tenant_id}";
    }

    public function getStatusOptions(): array
    {
        return [
            'queued' => 'En cola', 'sending' => 'Enviando', 'sent' => 'Enviado', 'delivered' => 'Entregado',
            'failed' => 'Fallido', 'undelivered' => 'No entregado', 'blocked' => 'Bloqueado (baja)', 'cancelled' => 'Cancelado',
        ];
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereNull('tenant_id');
    }
}
