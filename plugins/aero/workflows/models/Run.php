<?php namespace Aero\Workflows\Models;

use Model;

class Run extends Model
{
    public $table = 'aero_workflows_runs';

    public $guarded = [];

    protected $dates = ['started_at', 'finished_at'];

    public function decoded(string $field): mixed
    {
        $raw = $this->attributes[$field] ?? null;

        return is_string($raw) ? json_decode($raw, true) : $raw;
    }

    public $belongsTo = [
        'workflow' => [Workflow::class, 'key' => 'workflow_id'],
    ];

    public $hasMany = [
        'steps' => [RunStep::class, 'key' => 'run_id'],
    ];
}
