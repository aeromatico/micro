<?php namespace Aero\Shop\Models;

use Model;

class PanicAlert extends Model
{
    /** Horas que un aviso sin atender sigue visible. */
    public const VISIBLE_HOURS = 3;

    public $table = 'aero_shop_panic_alerts';

    public $fillable = ['tenant_id', 'order_id', 'table_label', 'label', 'ip', 'acknowledged_at', 'acknowledged_by'];

    protected $dates = ['acknowledged_at'];

    public $belongsTo = [
        'order' => [Order::class],
    ];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Sin atender y recientes: pasadas unas horas se da por olvidado y deja de molestar. */
    public function scopeActive($query)
    {
        return $query->whereNull('acknowledged_at')->where('created_at', '>=', now()->subHours(self::VISIBLE_HOURS));
    }
}

