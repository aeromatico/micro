<?php namespace Aero\Workspaces\Models;

use Model;

/** Copia «antes» de un cambio hecho por un agente (ver Classes\WebTools). */
class Revision extends Model
{
    public $table = 'aero_workspaces_revisions';

    public $fillable = ['tenant_id', 'subject_type', 'subject_id', 'action', 'before', 'summary', 'agreed_plan', 'undone_at'];

    protected $jsonable = ['before'];

    protected $dates = ['undone_at'];
}
