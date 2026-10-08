<?php namespace Aero\Workflows\Models;

use Model;

/** Estado anterior de un workflow, guardado antes de un cambio hecho por un agente. */
class Revision extends Model
{
    public $table = 'aero_workflows_revisions';

    public $fillable = ['workflow_id', 'tenant_id', 'version', 'name', 'trigger_type', 'trigger_config', 'graph', 'source', 'note'];

    public const KEEP = 20;
}
