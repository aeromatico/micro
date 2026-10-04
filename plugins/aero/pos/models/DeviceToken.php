<?php namespace Aero\Pos\Models;

use Model;

class DeviceToken extends Model
{
    public $table = 'aero_pos_device_tokens';

    public $fillable = ['tenant_id', 'user_id', 'terminal_id', 'name', 'token_hash', 'last_used_at', 'expires_at'];

    protected $dates = ['last_used_at', 'expires_at'];
}
