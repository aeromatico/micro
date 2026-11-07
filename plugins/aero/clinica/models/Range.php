<?php namespace Aero\Clinica\Models;

use Aero\Clinica\Classes\TenantOwned;
use Aero\Clinica\Classes\VitalsSchema;
use Model;

/**
 * Rango «bajo/alto» definido por el NEGOCIO (nadie los carga por defecto): por métrica y,
 * opcionalmente, sexo. Sin rango no se marca nada en rojo.
 */
class Range extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_clinica_ranges';

    public $fillable = ['tenant_id', 'sex', 'metric', 'low', 'high', 'source'];

    public $rules = [
        'sex'    => 'nullable|in:male,female',
        'metric' => 'required|string|max:48',
        'low'    => 'nullable|numeric',
        'high'   => 'nullable|numeric',
    ];

    public function getSexOptions(): array
    {
        return ['' => '— Ambos —', 'male' => 'Masculino', 'female' => 'Femenino'];
    }

    /** Signos vitales + analitos del negocio. */
    public function getMetricOptions(): array
    {
        $out = ['bmi' => 'IMC (kg/m²)']; // calculado: no se escribe, pero sí tiene rango
        foreach (VitalsSchema::editable() as $k => $f) {
            if (!isset($f['options'])) {
                $out[$k] = $f['label'] . ($f['unit'] ? " ({$f['unit']})" : '');
            }
        }
        $tenant = $this->tenant_id ?: \Aero\Clinica\Classes\CurrentTenant::id();
        if ($tenant) {
            foreach (Analyte::forTenant($tenant)->orderBy('sort')->get() as $a) {
                $out['analyte:' . $a->id] = $a->name . ($a->unit ? " ({$a->unit})" : '');
            }
        }

        return $out;
    }

    public function getSourceOptions(): array
    {
        return ['custom' => 'Del negocio', 'reference' => 'Referencia (borrador sin revisión médica)'];
    }

    public function beforeValidate(): void
    {
        // Un rango de referencia que el negocio edita pasa a ser suyo.
        $this->source = $this->source ?: 'custom';
        if ($this->exists && $this->source === 'reference' && $this->isDirty(['low', 'high', 'metric', 'sex'])) {
            $this->source = 'custom';
        }

        $this->sex = $this->sex ?: null;
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
