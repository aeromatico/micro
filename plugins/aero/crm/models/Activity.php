<?php namespace Aero\Crm\Models;

use Model;

class Activity extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    public $table = 'aero_crm_activities';

    public $fillable = [
        'tenant_id', 'related_type', 'related_id', 'type', 'subject',
        'description', 'due_at', 'completed_at', 'status', 'owner_id',
    ];

    public $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    public $rules = [
        'tenant_id'    => 'required|exists:aero_sites_tenants,id',
        'related_type' => 'nullable',
        'related_id'   => 'nullable|integer',
        'type'         => 'required|in:call,email,whatsapp,meeting,note,task',
        'subject'      => 'required|max:255',
        'status'       => 'in:pending,in_progress,completed,cancelled',
    ];

    protected $dates = ['due_at', 'completed_at'];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
        'owner'  => [\Backend\Models\User::class],
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->status === self::STATUS_COMPLETED) {
                $model->completed_at = $model->completed_at ?: now();
            }
            else {
                $model->completed_at = null;
            }
        });
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING     => 'Pendiente',
            self::STATUS_IN_PROGRESS => 'En curso',
            self::STATUS_COMPLETED   => 'Completada',
            self::STATUS_CANCELLED   => 'Cancelada',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return static::statusOptions()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_CANCELLED]);
    }

    public function related()
    {
        $class = $this->related_type;
        return class_exists($class) ? $class::find($this->related_id) : null;
    }
}
