<?php namespace Aero\Crm\Models;

use Model;

/** Copia «antes» de un cambio hecho por un agente en el CRM (ver Classes\AgentTools). */
class AgentChange extends Model
{
    public $table = 'aero_crm_agent_changes';

    public $guarded = [];

    public $jsonable = ['before'];

    protected $dates = ['undone_at'];
}
