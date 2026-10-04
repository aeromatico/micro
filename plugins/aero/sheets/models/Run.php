<?php namespace Aero\Sheets\Models;

use Model;

class Run extends Model
{
    public $table = 'aero_sheets_runs';

    public $guarded = [];

    public $jsonable = ['errors'];

    protected $dates = ['started_at', 'finished_at'];

    public $belongsTo = ['mapping' => [Mapping::class, 'key' => 'mapping_id']];
}
