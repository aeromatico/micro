<?php namespace Aero\Plan\Models;

use Model;

class Message extends Model
{
    public $table = 'aero_plan_messages';

    protected $guarded = ['id'];

    public $belongsTo = ['plan' => [Plan::class, 'key' => 'plan_id']];
}
