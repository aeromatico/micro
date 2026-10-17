<?php namespace Aero\Plan\Models;

use Model;

class Plan extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_plan_plans';

    protected $guarded = ['id'];

    protected $dates = ['started_at', 'paused_at', 'completed_at'];

    public $rules = ['tenant_id' => 'required|integer'];

    public $hasMany = [
        'steps'    => [Step::class, 'key' => 'plan_id', 'order' => 'number'],
        'messages' => [Message::class, 'key' => 'plan_id', 'order' => 'id'],
    ];

    public const STATUS = ['active' => 'En curso', 'paused' => 'En pausa', 'completed' => 'Completado'];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function getTenantNameAttribute(): string
    {
        if (class_exists(\Aero\Sites\Models\Tenant::class)) {
            return \Aero\Sites\Models\Tenant::find($this->tenant_id)?->name ?? "Tenant #{$this->tenant_id}";
        }

        return "Tenant #{$this->tenant_id}";
    }

    /** Número del paso abierto (o el último si el plan terminó). */
    public function getCurrentStepAttribute(): int
    {
        $open = $this->steps->first(fn ($s) => in_array($s->status, ['open', 'review', 'changes'], true));

        return $open ? (int) $open->number : 5;
    }

    public function getApprovedCountAttribute(): int
    {
        return $this->steps->where('status', 'approved')->count();
    }

    /** Día del plan (1 = el primero). El tiempo en pausa no cuenta. */
    public function getDayAttribute(): int
    {
        if (!$this->started_at) {
            return 1;
        }

        $end = $this->status === 'completed' && $this->completed_at
            ? $this->completed_at
            : ($this->status === 'paused' && $this->paused_at ? $this->paused_at : now());

        $seconds = $end->getTimestamp() - $this->started_at->getTimestamp() - (int) $this->paused_seconds;

        return max(1, (int) floor(max(0, $seconds) / 86400) + 1);
    }
}
