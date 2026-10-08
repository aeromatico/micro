<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

/** Horario semanal recurrente; el comando aero:gym-generate-sessions lo convierte en sesiones. */
class Schedule extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_gym_schedules';

    public $fillable = ['tenant_id', 'class_type_id', 'instructor_id', 'weekday', 'start_time', 'capacity', 'room', 'is_active'];

    public $rules = [
        'class_type_id' => 'required|integer',
        'weekday'       => 'required|integer|between:1,7',
        'start_time'    => 'required',
    ];

    public $belongsTo = [
        'classType'  => [ClassType::class, 'key' => 'class_type_id'],
        'instructor' => [Instructor::class, 'key' => 'instructor_id'],
    ];

    public static function weekdays(): array
    {
        return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    }

    public function getWeekdayOptions(): array
    {
        return self::weekdays();
    }

    public function getClassTypeIdOptions(): array
    {
        return ClassType::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getInstructorIdOptions(): array
    {
        return ['' => '— Sin instructor —'] + Instructor::visible()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['class_type_id' => ClassType::class, 'instructor_id' => Instructor::class]);
        if ($this->class_type_id && empty($this->tenant_id)) {
            $this->tenant_id = ClassType::whereKey($this->class_type_id)->value('tenant_id');
        }
    }
}
