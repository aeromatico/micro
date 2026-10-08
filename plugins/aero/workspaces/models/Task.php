<?php namespace Aero\Workspaces\Models;

use Model;

/**
 * Encargo de la Oficina de un tenant. El `plan` dice quién hace qué y cuánto
 * cuesta; el estado avanza solo por el reloj porque la ejecución es simulada.
 */
class Task extends Model
{
    public $table = 'aero_workspaces_tasks';

    public $fillable = [
        'tenant_id', 'user_id', 'orchestrator_id', 'brief', 'status', 'estimated_points', 'charged_points',
        'credit_transaction_id', 'plan', 'source', 'started_at', 'finished_at', 'refunded_at',
    ];

    public $jsonable = ['plan'];

    protected $dates = ['started_at', 'finished_at', 'refunded_at'];

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /** Marca como terminados los encargos cuyo reloj ya venció. Devuelve cuántos. */
    public static function settleDue(?int $tenantId = null): int
    {
        return static::where('status', 'running')
            ->where('finished_at', '<=', now())
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->update(['status' => 'done', 'updated_at' => now()]);
    }
}
