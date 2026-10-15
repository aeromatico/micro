<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

class Course extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_school_courses';

    public $fillable = ['tenant_id', 'year_id', 'name', 'level', 'grade', 'parallel', 'shift', 'tutor', 'capacity', 'is_active'];

    public $rules = [
        'name'  => 'required|string|max:64',
        'level' => 'required|in:inicial,primaria,secundaria',
    ];

    public $belongsTo = ['year' => [Year::class, 'key' => 'year_id']];

    public $hasMany = [
        'students' => [Student::class, 'key' => 'course_id'],
        'subjects' => [Subject::class, 'key' => 'course_id'],
    ];

    public const LEVELS = ['inicial' => 'Inicial', 'primaria' => 'Primaria', 'secundaria' => 'Secundaria'];

    public function getLevelOptions(): array
    {
        return self::LEVELS;
    }

    public function getShiftOptions(): array
    {
        return ['manana' => 'Mañana', 'tarde' => 'Tarde', 'noche' => 'Noche'];
    }

    public function getYearIdOptions(): array
    {
        return Year::visible()->orderByDesc('id')->pluck('name', 'id')->all();
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? (string) $this->level;
    }

    /** Evaluación cualitativa (Inicial) en vez de cuantitativa 0-100. */
    public function isQualitative(): bool
    {
        return $this->level === 'inicial';
    }
}
