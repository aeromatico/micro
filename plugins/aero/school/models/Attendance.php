<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

class Attendance extends Model
{
    use TenantOwned;

    public $table = 'aero_school_attendances';

    public $fillable = ['tenant_id', 'student_id', 'course_id', 'date', 'status', 'note', 'notified_at', 'recorded_by'];

    /** `date` queda como cadena Y-m-d: se usa para buscar el registro del día. */
    public $dates = ['notified_at'];

    public $belongsTo = [
        'student' => [Student::class, 'key' => 'student_id'],
        'course'  => [Course::class, 'key' => 'course_id'],
    ];

    public const STATUSES = ['present' => 'Presente', 'absent' => 'Falta', 'late' => 'Atraso', 'excused' => 'Licencia'];

    public function getStudentNameAttribute(): string
    {
        return (string) $this->student?->full_name;
    }
}
