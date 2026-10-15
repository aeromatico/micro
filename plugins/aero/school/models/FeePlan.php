<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

/** Concepto de cobro: matrícula (1 cuota) o pensión (N cuotas mensuales) por curso o nivel. */
class FeePlan extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_school_fee_plans';

    public $fillable = ['tenant_id', 'year_id', 'name', 'kind', 'amount', 'course_id', 'level', 'installments', 'first_month', 'due_day', 'is_active'];

    public $rules = [
        'name'         => 'required|string|max:255',
        'kind'         => 'required|in:matricula,pension,otro',
        'amount'       => 'required|numeric|min:0',
        'installments' => 'required|integer|min:1|max:12',
        'first_month'  => 'required|integer|min:1|max:12',
        'due_day'      => 'required|integer|min:1|max:28',
    ];

    public $belongsTo = [
        'year'   => [Year::class, 'key' => 'year_id'],
        'course' => [Course::class, 'key' => 'course_id'],
    ];

    public const KINDS = ['matricula' => 'Matrícula', 'pension' => 'Pensión mensual', 'otro' => 'Otro'];

    public function beforeValidate(): void
    {
        foreach (['course_id', 'level', 'year_id'] as $f) {
            if ($this->{$f} === '') {
                $this->{$f} = null;
            }
        }
    }

    public function getKindOptions(): array
    {
        return self::KINDS;
    }

    public function getYearIdOptions(): array
    {
        return Year::visible()->orderByDesc('id')->pluck('name', 'id')->all();
    }

    public function getCourseIdOptions(): array
    {
        return ['' => '— Todos los cursos —'] + Course::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getLevelOptions(): array
    {
        return ['' => '— Todos los niveles —'] + Course::LEVELS;
    }

    public function getFirstMonthOptions(): array
    {
        return array_combine(range(1, 12), ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']);
    }

    /** Alumnos a los que aplica (inscritos, del curso o nivel indicado). */
    public function targetStudents()
    {
        return Student::where('tenant_id', $this->tenant_id)->where('status', 'active')
            ->when($this->course_id, fn ($q) => $q->where('course_id', $this->course_id))
            ->when(!$this->course_id && $this->level, fn ($q) => $q->whereIn('course_id',
                Course::where('tenant_id', $this->tenant_id)->where('level', $this->level)->pluck('id')))
            ->when($this->year_id, fn ($q) => $q->whereIn('course_id', Course::where('year_id', $this->year_id)->pluck('id')));
    }
}
