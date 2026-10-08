<?php namespace Aero\Workflows\Models;

use Model;

class RunStep extends Model
{
    public $table = 'aero_workflows_run_steps';

    public $guarded = [];

    public $belongsTo = [
        'run' => [Run::class, 'key' => 'run_id'],
    ];
}
