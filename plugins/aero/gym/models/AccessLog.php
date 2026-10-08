<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

class AccessLog extends Model
{
    use TenantOwned;

    public $table = 'aero_gym_access_logs';

    public $fillable = ['tenant_id', 'member_id', 'credential', 'method', 'granted', 'reason', 'session_id'];

    public $belongsTo = [
        'member' => [Member::class, 'key' => 'member_id'],
    ];
}
