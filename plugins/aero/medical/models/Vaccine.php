<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\TenantOwned;
use Model;

/**
 * Vacuna del catálogo del negocio con su esquema como DATOS (dosis y días entre una y otra,
 * refuerzo periódico). En animal puede ir por especie. Nadie la carga de fábrica: el esquema
 * es una decisión clínica del negocio (y en Bolivia, del esquema oficial vigente).
 */
class Vaccine extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_vaccines';

    public $fillable = ['tenant_id', 'name', 'species', 'schedule', 'booster_every_days', 'is_active'];

    public $jsonable = ['schedule'];

    public $casts = ['is_active' => 'boolean'];

    public $rules = [
        'name'               => 'required|string|max:255',
        'booster_every_days' => 'nullable|integer|min:1|max:7300',
    ];

    public function getSpeciesOptions(): array
    {
        return ['' => '— Todas —'] + Mode::species();
    }

    public function beforeValidate(): void
    {
        $this->species = $this->species ?: null;
        $this->booster_every_days = $this->booster_every_days ?: null;

        $doses = [];
        foreach (array_values(array_filter((array) $this->schedule, fn ($d) => trim((string) ($d['label'] ?? '')) !== '')) as $i => $d) {
            $after = $d['after_days'] ?? null;
            if ($i > 0 && ($after === null || $after === '' || !is_numeric($after) || (int) $after < 0)) {
                throw new \ApplicationException('La dosis «' . $d['label'] . '» necesita los días desde la dosis anterior.');
            }
            $doses[] = ['label' => trim((string) $d['label']), 'after_days' => $i > 0 ? (int) $after : null];
        }
        $this->schedule = $doses;
    }

    /** Dosis (0-based) del esquema, o null si ya se completó. */
    public function doseAt(int $index): ?array
    {
        return ((array) $this->schedule)[$index] ?? null;
    }

    /** Días hasta la dosis siguiente a la recién aplicada (índice aplicado = $index), o el refuerzo. */
    public function daysAfterDose(int $index): ?int
    {
        $next = $this->doseAt($index + 1);
        if ($next) {
            return (int) $next['after_days'];
        }

        return $this->booster_every_days ? (int) $this->booster_every_days : null;
    }

    public function appliesTo(?string $species): bool
    {
        return !$this->species || $this->species === $species;
    }
}
