<?php namespace Aero\Plan\Models;

use Model;

class Step extends Model
{
    public $table = 'aero_plan_steps';

    protected $guarded = ['id'];

    protected $dates = ['submitted_at', 'approved_at'];

    protected $jsonable = ['answers'];

    public $belongsTo = ['plan' => [Plan::class, 'key' => 'plan_id']];

    public const STATUS = [
        'locked'   => 'Bloqueado',
        'open'     => 'En curso',
        'review'   => 'En revisión',
        'changes'  => 'Cambios pedidos',
        'approved' => 'Aprobado',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /** El dueño puede editar sus respuestas solo en estos estados. */
    public function isEditable(): bool
    {
        return in_array($this->status, ['open', 'changes'], true);
    }

    public function answer(string $key, $default = null)
    {
        return ($this->answers ?? [])[$key] ?? $default;
    }
}
