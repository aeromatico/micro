<?php namespace Aero\Medical\Models;

use Aero\Medical\Classes\TenantOwned;
use Model;

/** Catálogo propio de medicamentos del negocio (en animal es el único catálogo). */
class Medication extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_medical_medications';

    public $fillable = ['tenant_id', 'name', 'form', 'concentration', 'notes', 'is_active'];

    public $casts = ['is_active' => 'boolean'];

    public $rules = ['name' => 'required|string|max:255', 'form' => 'nullable|string|max:255', 'concentration' => 'nullable|string|max:255', 'notes' => 'nullable|string|max:255'];

    public static function search(int $tenantId, string $q, int $limit = 15)
    {
        $q = trim($q);
        $query = static::forTenant($tenantId)->where('is_active', true);
        if ($q !== '') {
            foreach (preg_split('/\s+/', $q) as $w) {
                $query->where('name', 'like', '%' . addcslashes($w, '%_\\') . '%');
            }
        }

        return $query->orderBy('name')->limit($limit)->get();
    }
}
