<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/** Catálogo editable de exámenes que el negocio puede pedir (hemograma, glucosa, etc.). */
class ExamCatalog extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_exam_catalog';

    public $fillable = ['tenant_id', 'name', 'category', 'sort', 'is_active'];

    public $casts = ['is_active' => 'boolean'];

    public $rules = ['name' => 'required|string|max:255', 'category' => 'required|in:lab,imaging,other'];

    public const CATEGORIES = ['lab' => 'Laboratorio', 'imaging' => 'Imagen', 'other' => 'Otro'];

    /** Solo nombres de práctica habitual; sin valores clínicos. El negocio los edita. */
    public const DEFAULTS = [
        ['Hemograma completo', 'lab'], ['Glucosa en ayunas', 'lab'], ['Hemoglobina glicosilada (HbA1c)', 'lab'],
        ['Creatinina', 'lab'], ['Perfil lipídico', 'lab'], ['Examen general de orina', 'lab'],
        ['Radiografía', 'imaging'], ['Ecografía', 'imaging'],
    ];

    public function getCategoryOptions(): array
    {
        return self::CATEGORIES;
    }

    public static function ensureDefaults(int $tenantId): void
    {
        if (static::where('tenant_id', $tenantId)->exists()) {
            return;
        }
        foreach (self::DEFAULTS as $i => [$name, $cat]) {
            static::create(['tenant_id' => $tenantId, 'name' => $name, 'category' => $cat, 'sort' => $i, 'is_active' => true]);
        }
    }
}
