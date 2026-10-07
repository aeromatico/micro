<?php namespace Aero\Workspaces\Models;

use Model;

/** Un mensaje de la conversación de un tenant con un agente real. */
class Message extends Model
{
    public $table = 'aero_workspaces_messages';

    public $fillable = ['tenant_id', 'staff_id', 'user_id', 'role', 'content', 'status', 'meta', 'error'];

    public $jsonable = ['meta'];

    public function scopeThread($query, int $tenantId, int $staffId)
    {
        return $query->where('tenant_id', $tenantId)->where('staff_id', $staffId);
    }
}
