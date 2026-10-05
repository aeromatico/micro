<?php namespace Aero\Workspaces\Models;

use Model;

/** Tarifa de contratación. 0 = el tenant no paga al contratar. */
class StaffRate extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_workspaces_staff_rates';

    public $fillable = ['staff_id', 'hire_fee'];

    public $rules = [
        'staff_id' => 'required|integer',
        'hire_fee' => 'required|numeric|min:0',
    ];
}
