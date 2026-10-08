<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\CurrentTenant;
use Aero\Gym\Classes\TenantOwned;
use Model;

/** Tipo de clase (yoga, spinning…). La página promocional vive en aero/sites. */
class ClassType extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_gym_class_types';

    public $fillable = ['tenant_id', 'name', 'description', 'duration_minutes', 'default_capacity', 'color', 'page_id', 'is_active'];

    public $rules = [
        'name'             => 'required|string|max:255',
        'duration_minutes' => 'required|integer|min:5',
        'default_capacity' => 'required|integer|min:1',
    ];

    public $hasMany = ['sessions' => [ClassSession::class, 'key' => 'class_type_id']];

    /** Relación simple a Aero\Sites\Models\Page, sin acoplamiento duro. */
    public function beforeSave(): void
    {
        if ($this->page_id && class_exists(\Aero\Sites\Models\Page::class)) {
            $tenant = $this->tenant_id ?: CurrentTenant::id();
            if (!\Aero\Sites\Models\Page::where('tenant_id', $tenant)->whereKey($this->page_id)->exists()) {
                throw new \ApplicationException('La página elegida no pertenece a este gimnasio.');
            }
        }
    }

    public function getPageAttribute()
    {
        if (!$this->page_id || !class_exists(\Aero\Sites\Models\Page::class)) {
            return null;
        }

        return \Aero\Sites\Models\Page::where('tenant_id', $this->tenant_id)->find($this->page_id);
    }

    public function getPageIdOptions(): array
    {
        if (!class_exists(\Aero\Sites\Models\Page::class)) {
            return [];
        }

        $q = \Aero\Sites\Models\Page::query();
        if (!CurrentTenant::isAdmin()) {
            $id = CurrentTenant::id();
            $id ? $q->where('tenant_id', $id) : $q->whereRaw('1 = 0');
        } elseif ($this->tenant_id) {
            $q->where('tenant_id', $this->tenant_id);
        }

        return ['' => '— Sin página —'] + $q->orderBy('title')->pluck('title', 'id')->all();
    }
}
