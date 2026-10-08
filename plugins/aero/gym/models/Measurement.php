<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

/** Medición corporal de un socio en una fecha (una por día). El IMC se calcula, no se guarda. */
class Measurement extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_gym_measurements';

    public $fillable = [
        'tenant_id', 'member_id', 'measured_on', 'weight_kg', 'height_cm', 'body_fat_pct', 'muscle_pct',
        'waist_cm', 'chest_cm', 'hip_cm', 'arm_cm', 'thigh_cm', 'notes', 'source',
    ];

    protected $dates = ['measured_on'];

    public $rules = [
        'member_id'    => 'required|integer',
        'measured_on'  => 'required|date|before_or_equal:today|after:1990-01-01',
        'weight_kg'    => 'required|numeric|between:20,400',
        'height_cm'    => 'nullable|numeric|between:80,250',
        'body_fat_pct' => 'nullable|numeric|between:2,70',
        'muscle_pct'   => 'nullable|numeric|between:5,80',
        'waist_cm'     => 'nullable|numeric|between:30,250',
        'chest_cm'     => 'nullable|numeric|between:40,250',
        'hip_cm'       => 'nullable|numeric|between:40,250',
        'arm_cm'       => 'nullable|numeric|between:10,100',
        'thigh_cm'     => 'nullable|numeric|between:20,150',
        'notes'        => 'nullable|string|max:500',
    ];

    public $customMessages = [
        'required'                => 'Falta el campo :attribute.',
        'numeric'                 => 'El campo :attribute debe ser un número.',
        'between.numeric'         => 'El campo :attribute debe estar entre :min y :max.',
        'date'                    => 'La :attribute no es válida.',
        'before_or_equal'         => 'La :attribute no puede ser futura.',
        'after'                   => 'La :attribute no es válida.',
        'max'                     => 'El campo :attribute es demasiado largo.',
    ];

    public $attributeNames = [
        'measured_on' => 'fecha', 'weight_kg' => 'peso', 'height_cm' => 'estatura', 'body_fat_pct' => '% de grasa',
        'muscle_pct' => '% de músculo', 'waist_cm' => 'cintura', 'chest_cm' => 'pecho', 'hip_cm' => 'cadera',
        'arm_cm' => 'brazo', 'thigh_cm' => 'muslo',
    ];

    public $belongsTo = ['member' => [Member::class, 'key' => 'member_id']];

    public function getMemberIdOptions(): array
    {
        return Member::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getSourceOptions(): array
    {
        return ['member' => 'El socio', 'staff' => 'El gimnasio'];
    }

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['member_id' => Member::class]);
        if ($this->member_id && empty($this->tenant_id)) {
            $this->tenant_id = Member::whereKey($this->member_id)->value('tenant_id');
        }
        // Estatura: si no viene, la última conocida del socio.
        if (empty($this->height_cm) && $this->member_id) {
            $this->height_cm = static::where('member_id', $this->member_id)->whereNotNull('height_cm')
                ->orderByDesc('measured_on')->value('height_cm');
        }
    }

    /** IMC = peso / estatura². Null sin estatura. */
    public function getBmiAttribute(): ?float
    {
        return self::bmi((float) $this->weight_kg, $this->height_cm ? (float) $this->height_cm : null);
    }

    public function getBmiLabelAttribute(): ?string
    {
        return self::bmiCategory($this->bmi)['label'] ?? null;
    }

    public static function bmi(float $kg, ?float $cm): ?float
    {
        if (!$cm || $cm <= 0 || $kg <= 0) {
            return null;
        }

        return round($kg / (($cm / 100) ** 2), 1);
    }

    /** Categorías de la OMS (adultos). */
    public static function bmiCategory(?float $bmi): ?array
    {
        if ($bmi === null) {
            return null;
        }

        return match (true) {
            $bmi < 18.5 => ['key' => 'low', 'label' => 'Bajo peso', 'color' => '#d9822b'],
            $bmi < 25   => ['key' => 'normal', 'label' => 'Peso normal', 'color' => '#2e9e5b'],
            $bmi < 30   => ['key' => 'over', 'label' => 'Sobrepeso', 'color' => '#d9822b'],
            default     => ['key' => 'obese', 'label' => 'Obesidad', 'color' => '#c0392b'],
        };
    }
}
