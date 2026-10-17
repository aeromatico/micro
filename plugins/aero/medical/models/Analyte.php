<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/** Resultados que el negocio anota a mano y siguen en la hoja (glucosa, HbA1c…). Nombre y unidad editables. */
class Analyte extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_analytes';

    public $fillable = ['tenant_id', 'name', 'unit', 'sort', 'is_active'];

    public $casts = ['is_active' => 'boolean'];

    public $rules = ['name' => 'required|string|max:255', 'unit' => 'nullable|string|max:32'];

    /** Nombres y unidades de uso corriente; ningún valor de referencia. El negocio los edita. */
    public const DEFAULTS = [
        ['Glucosa en ayunas', 'mg/dL'], ['Hemoglobina glicosilada (HbA1c)', '%'], ['Creatinina', 'mg/dL'],
        ['Hemoglobina', 'g/dL'], ['Colesterol total', 'mg/dL'], ['Triglicéridos', 'mg/dL'],
    ];

    public static function ensureDefaults(int $tenantId): void
    {
        if (static::where('tenant_id', $tenantId)->exists()) {
            return;
        }
        foreach (self::DEFAULTS as $i => [$name, $unit]) {
            static::create(['tenant_id' => $tenantId, 'name' => $name, 'unit' => $unit, 'sort' => $i, 'is_active' => true]);
        }
    }
}
