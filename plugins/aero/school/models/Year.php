<?php namespace Aero\School\Models;

use Aero\School\Classes\TenantOwned;
use Model;

/** Gestión escolar (año lectivo). Solo una es la vigente por colegio. */
class Year extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_school_years';

    public $fillable = ['tenant_id', 'name', 'starts_on', 'ends_on', 'is_current', 'periods'];

    public $rules = ['name' => 'required|string|max:32', 'periods' => 'nullable|integer|min:1|max:6'];

    public $dates = ['starts_on', 'ends_on'];

    public $hasMany = ['courses' => [Course::class, 'key' => 'year_id']];

    public function afterSave(): void
    {
        if ($this->is_current) {
            static::where('tenant_id', $this->tenant_id)->where('id', '!=', $this->id)->update(['is_current' => false]);
        }
    }

    public static function currentFor(?int $tenantId): ?self
    {
        return static::where('tenant_id', $tenantId)->where('is_current', true)->first()
            ?: static::where('tenant_id', $tenantId)->orderByDesc('id')->first();
    }
}
