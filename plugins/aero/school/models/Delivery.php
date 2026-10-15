<?php namespace Aero\School\Models;

use Model;

class Delivery extends Model
{
    public $table = 'aero_school_deliveries';

    public $timestamps = false;

    public $fillable = ['announcement_id', 'guardian_id', 'phone', 'status', 'error', 'created_at'];

    public $belongsTo = [
        'announcement' => [Announcement::class, 'key' => 'announcement_id'],
        'guardian'     => [Guardian::class, 'key' => 'guardian_id'],
    ];
}
