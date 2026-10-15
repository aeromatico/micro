<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

class Student extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Purgeable;
    use TenantOwned;

    /** Campos del formulario que no son columnas (los lee el controlador). */
    public $purgeable = ['guardian_id', 'guardian_name', 'guardian_phone', 'guardian_email', 'guardian_relationship'];

    public $table = 'aero_school_students';

    public $fillable = [
        'tenant_id', 'rude', 'document', 'first_name', 'paternal_surname', 'maternal_surname', 'birthdate', 'sex', 'course_id',
        'status', 'discount_pct', 'discount_note', 'enrolled_on', 'notes',
    ];

    public $rules = [
        'first_name'   => 'required|string|max:255',
        'status'       => 'in:active,retired,graduated',
        'discount_pct' => 'nullable|numeric|min:0|max:100',
    ];

    public $dates = ['birthdate', 'enrolled_on'];

    public $belongsTo = ['course' => [Course::class, 'key' => 'course_id']];

    public $hasMany = [
        'charges'     => [Charge::class, 'key' => 'student_id'],
        'payments'    => [Payment::class, 'key' => 'student_id'],
        'attendances' => [Attendance::class, 'key' => 'student_id'],
        'grades'      => [Grade::class, 'key' => 'student_id'],
    ];

    public $belongsToMany = [
        'guardians' => [Guardian::class, 'table' => 'aero_school_student_guardian', 'key' => 'student_id', 'otherKey' => 'guardian_id',
            'pivot' => ['relationship', 'is_primary', 'is_financial']],
    ];

    public function beforeValidate(): void
    {
        if ($this->discount_pct === '' || $this->discount_pct === null) {
            $this->discount_pct = 0;
        }
    }

    /** "Paterno Materno Nombres": como en las listas oficiales. */
    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->paternal_surname, $this->maternal_surname, $this->first_name])));
    }

    public function getStatusOptions(): array
    {
        return ['active' => 'Inscrito', 'retired' => 'Retirado', 'graduated' => 'Egresado'];
    }

    public function getSexOptions(): array
    {
        return ['' => '—', 'F' => 'Femenino', 'M' => 'Masculino'];
    }

    public function getCourseIdOptions(): array
    {
        return Course::visible()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getGuardianIdOptions(): array
    {
        return Guardian::visible()->orderBy('name')->get()->mapWithKeys(fn ($g) => [$g->id => $g->name . ($g->phone ? ' · ' . $g->phone : '')])->all();
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeSearch($q, string $term)
    {
        $like = '%' . $term . '%';

        return $q->where(fn ($w) => $w->where('first_name', 'like', $like)->orWhere('paternal_surname', 'like', $like)
            ->orWhere('maternal_surname', 'like', $like)->orWhere('rude', 'like', $like)->orWhere('document', 'like', $like));
    }

    /** Apoderados que reciben la cobranza (si ninguno está marcado, todos). */
    public function financialGuardians()
    {
        $all = $this->guardians()->get();
        $fin = $all->filter(fn ($g) => (bool) $g->pivot->is_financial);

        return $fin->isNotEmpty() ? $fin : $all;
    }

    public function primaryGuardian(): ?Guardian
    {
        $all = $this->guardians()->get();

        return $all->first(fn ($g) => (bool) $g->pivot->is_primary) ?: $all->first();
    }

    /** Lo que debe hoy: cuotas con saldo (vencidas o no) / solo vencidas. */
    public function pendingBalance(): float
    {
        return round((float) $this->charges()->whereIn('status', ['pending', 'partial'])
            ->sum(\DB::raw('amount - discount - paid_amount')), 2);
    }

    public function overdueBalance(): float
    {
        return round((float) $this->charges()->whereIn('status', ['pending', 'partial'])->whereDate('due_on', '<', today())
            ->sum(\DB::raw('amount - discount - paid_amount')), 2);
    }
}
