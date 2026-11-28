<?php namespace Aero\Church\Models;

use Model;

/**
 * Horario semanal de reuniones («Martes 3:45 a 6:00, reunión femenil»). No es un evento con fecha ni
 * inscripción: se repite cada semana. Las horas son texto HH:MM en hora local de la iglesia.
 */
class ServiceTime extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const DAYS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public $table = 'aero_church_service_times';

    public static $audited = true;
    public static $auditLabel = 'horario de reunión';
    public static $auditType = 'service_time';

    public $attributes = ['is_active' => true];

    public $fillable = ['tenant_id', 'branch_id', 'title', 'day', 'starts_at', 'ends_at', 'location', 'note', 'is_active'];

    public $rules = [
        'title'     => 'required|string|max:120',
        'day'       => 'required|integer|between:1,7',
        'starts_at' => 'required',
        'location'  => 'nullable|string|max:150',
        'note'      => 'nullable|string|max:200',
    ];

    public $customMessages = [
        'title.required'     => 'Escribe el nombre de la reunión.',
        'day.required'       => 'Elige el día.',
        'starts_at.required' => 'Escribe la hora de inicio.',
    ];

    public $belongsTo = [
        'branch' => [Branch::class, 'key' => 'branch_id'],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['branch_id' => Branch::class]);

        foreach (['starts_at' => 'inicio', 'ends_at' => 'fin'] as $field => $label) {
            if (trim((string) $this->{$field}) === '') {
                $this->{$field} = null;
                continue;
            }
            $time = self::parseTime((string) $this->{$field});
            if ($time === null) {
                throw new \ValidationException([$field => "La hora de {$label} no se entiende. Escríbela como 15:45 o 3:45 pm."]);
            }
            $this->{$field} = $time;
        }

        if ($this->starts_at && $this->ends_at && $this->ends_at <= $this->starts_at) {
            throw new \ValidationException(['ends_at' => 'La hora de fin debe ser posterior a la de inicio (si es de la tarde, escribe «pm» o usa 24 horas).']);
        }
    }

    public function beforeSave(): void
    {
        $this->week_position = (int) $this->day * 10000 + (int) str_replace(':', '', (string) $this->starts_at);
    }

    /** «15:45», «3:45 pm», «7 p. m.», «9.30» → «HH:MM»; null si no es una hora. */
    public static function parseTime(string $value): ?string
    {
        $v = mb_strtolower(trim($value));
        $v = preg_replace('/\s*([ap])\s*\.?\s*m\s*\.?$/u', '$1m', $v);
        if (!preg_match('/^(\d{1,2})(?:\s*[:.h]\s*(\d{2}))?\s*(am|pm)?$/u', $v, $m)) {
            return null;
        }
        $hour = (int) $m[1];
        $minute = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        $half = $m[3] ?? '';
        if ($half !== '') {
            if ($hour < 1 || $hour > 12) {
                return null;
            }
            $hour = $hour % 12 + ($half === 'pm' ? 12 : 0);
        }

        return $hour <= 23 && $minute <= 59 ? sprintf('%02d:%02d', $hour, $minute) : null;
    }

    public function scopeActive($query)
    {
        return $query->where($this->getTable() . '.is_active', true);
    }

    public function getDayOptions(): array
    {
        return self::DAYS;
    }

    public function getBranchIdOptions(): array
    {
        return Branch::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getDayLabelAttribute(): string
    {
        return self::DAYS[(int) $this->day] ?? '';
    }
}
