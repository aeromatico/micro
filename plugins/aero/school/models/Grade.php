<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

/**
 * Calificación de un área en un trimestre. Modelo del Sistema Educativo
 * Plurinacional: Ser (10) + Saber (35) + Hacer (35) + Decidir (10) +
 * autoevaluación Ser (5) y Decidir (5) = 100.
 */
class Grade extends Model
{
    use TenantOwned;

    public $table = 'aero_school_grades';

    public $fillable = [
        'tenant_id', 'student_id', 'subject_id', 'course_id', 'year_id', 'period', 'ser', 'saber', 'hacer', 'decidir',
        'auto_ser', 'auto_decidir', 'total', 'qualitative',
    ];

    public $belongsTo = [
        'student' => [Student::class, 'key' => 'student_id'],
        'subject' => [Subject::class, 'key' => 'subject_id'],
    ];

    /** Puntaje máximo de cada dimensión. */
    public const MAX = ['ser' => 10, 'saber' => 35, 'hacer' => 35, 'decidir' => 10, 'auto_ser' => 5, 'auto_decidir' => 5];

    public function recalculate(): self
    {
        $values = array_map(fn ($k) => $this->{$k}, array_keys(self::MAX));
        $this->total = count(array_filter($values, fn ($v) => $v !== null && $v !== '')) ? round(array_sum(array_map('floatval', $values)), 0) : null;

        return $this;
    }
}
