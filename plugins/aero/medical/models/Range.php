<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\TenantOwned;
use Aero\Medical\Classes\VitalsSchema;
use Model;

/**
 * Rango «bajo/alto» definido por el NEGOCIO (nadie los carga por defecto): por modo, métrica y,
 * en animal, especie. Sin rango no se marca nada en rojo.
 */
class Range extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_ranges';

    public $fillable = ['tenant_id', 'mode', 'species', 'metric', 'low', 'high'];

    public $rules = [
        'mode'   => 'required|in:human,animal',
        'metric' => 'required|string|max:48',
        'low'    => 'nullable|numeric',
        'high'   => 'nullable|numeric',
    ];

    public function getModeOptions(): array
    {
        return Mode::modes();
    }

    public function getSpeciesOptions(): array
    {
        return ['' => '— Todas —'] + Mode::species();
    }

    /** Signos vitales del modo + analitos del negocio. */
    public function getMetricOptions(): array
    {
        $mode = $this->mode ?: (Mode::forTenant($this->tenant_id ?: \Aero\Medical\Classes\CurrentTenant::id()) ?: Mode::HUMAN);
        $out = [];
        foreach (VitalsSchema::editable($mode) as $k => $f) {
            if (!isset($f['options'])) {
                $out[$k] = $f['label'] . ($f['unit'] ? " ({$f['unit']})" : '');
            }
        }
        $tenant = $this->tenant_id ?: \Aero\Medical\Classes\CurrentTenant::id();
        if ($tenant) {
            foreach (Analyte::forTenant($tenant)->orderBy('sort')->get() as $a) {
                $out['analyte:' . $a->id] = $a->name . ($a->unit ? " ({$a->unit})" : '');
            }
        }

        return $out;
    }

    public function beforeValidate(): void
    {
        $this->species = $this->species ?: null;
        if ($this->mode !== Mode::ANIMAL) {
            $this->species = null;
        }
        $this->low = $this->low === '' ? null : $this->low;
        $this->high = $this->high === '' ? null : $this->high;
        if ($this->low === null && $this->high === null) {
            throw new \ApplicationException('Indique al menos un límite, bajo o alto.');
        }
        if ($this->low !== null && $this->high !== null && (float) $this->low > (float) $this->high) {
            throw new \ApplicationException('El límite bajo no puede ser mayor que el alto.');
        }
    }
}
