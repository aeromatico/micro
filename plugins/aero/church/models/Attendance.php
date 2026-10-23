<?php namespace Aero\Church\Models;

use Model;

/** Asistencia de un miembro a una reunión. */
class Attendance extends Model
{
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_attendances';

    public $fillable = ['tenant_id', 'meeting_id', 'member_id', 'present'];

    public $belongsTo = [
        'meeting' => [Meeting::class, 'key' => 'meeting_id'],
        'member'  => [Member::class, 'key' => 'member_id'],
    ];
}
