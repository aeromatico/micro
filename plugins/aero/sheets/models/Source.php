<?php namespace Aero\Sheets\Models;

use Aero\Sheets\Classes\SourceRegistry;
use Model;

/** Modelo sincronizable y los campos que el superadmin autorizó. */
class Source extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sheets_sources';

    public $fillable = ['model_class', 'label', 'fields', 'tenant_access', 'is_enabled'];

    public $jsonable = ['fields'];

    public $rules = [
        'model_class' => 'required',
        'label'       => 'required',
    ];

    public $hasMany = ['mappings' => [Mapping::class, 'key' => 'source_id']];

    public function beforeValidate(): void
    {
        $this->rules['model_class'] = 'required|unique:aero_sheets_sources,model_class,' . ($this->id ?: 'NULL');

        if ($this->model_class && !SourceRegistry::isAllowedClass($this->model_class)) {
            throw new \ValidationException(['model_class' => 'Debe ser un modelo existente de un plugin Aero (Aero\\Plugin\\Models\\…).']);
        }
    }

    public function beforeSave(): void
    {
        // Un modelo sin tenant_id no puede abrirse a los tenants.
        if ($this->tenant_access && !SourceRegistry::hasTenantColumn($this->model_class)) {
            $this->tenant_access = false;
        }
    }

    /** @return array<string, array> campo => definición, solo los habilitados. */
    public function fieldMap(?string $direction = null): array
    {
        $out = [];
        foreach ((array) $this->fields as $f) {
            if (empty($f['key'])) {
                continue;
            }
            if ($direction === 'import' && empty($f['import'])) {
                continue;
            }
            if ($direction === 'export' && empty($f['export'])) {
                continue;
            }
            $out[$f['key']] = $f;
        }

        return $out;
    }

    public function getFieldsCountAttribute(): int
    {
        return count((array) $this->fields);
    }

    public function scopeAccessibleBy($query, bool $isAdmin)
    {
        $query->where('is_enabled', true);

        return $isAdmin ? $query : $query->where('tenant_access', true);
    }
}
