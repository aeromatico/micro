<?php namespace Aero\Workspaces\Models;

use Model;

/** Tarifa por tipo de tarea. 0 = la tarea no cobra. */
class TaskRate extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_workspaces_task_rates';

    public $fillable = ['staff_id', 'task_type', 'fee'];

    public $rules = [
        'staff_id'  => 'required|integer',
        'task_type' => 'required|alpha_dash|max:64',
        'fee'       => 'required|numeric|min:0',
    ];
}
