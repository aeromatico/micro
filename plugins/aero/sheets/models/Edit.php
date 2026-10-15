<?php namespace Aero\Sheets\Models;

use Model;

/** Edición directa de celdas hecha por un agente, con el «antes» para deshacerla. */
class Edit extends Model
{
    public $table = 'aero_sheets_edits';

    public $guarded = [];

    public $jsonable = ['ranges'];

    protected $dates = ['undone_at'];
}
