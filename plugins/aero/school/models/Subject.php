<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

/** Área / materia de un curso, dentro de un campo de saberes. */
class Subject extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_school_subjects';

    public $fillable = ['tenant_id', 'course_id', 'name', 'field', 'teacher', 'sort_order'];

    public $rules = ['name' => 'required|string|max:255', 'course_id' => 'required|integer'];

    public $belongsTo = ['course' => [Course::class, 'key' => 'course_id']];

    public function beforeSave(): void
    {
        if ($this->course_id && !$this->tenant_id) {
            $this->tenant_id = Course::whereKey($this->course_id)->value('tenant_id');
        }
    }

    public function getCourseIdOptions(): array
    {
        return Course::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getFieldOptions(): array
    {
        return \Aero\School\Classes\Curriculum::FIELDS;
    }
}
