<?php namespace Aero\Pos\Models;

use Model;
use Str;

class Terminal extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_pos_terminals';

    public $fillable = ['tenant_id', 'name', 'code', 'is_active'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id',
        'name'      => 'required|min:2|max:80',
        'code'      => 'nullable|alpha_dash|max:30',
    ];

    public $hasMany = [
        'shifts' => [Shift::class],
    ];

    public function beforeValidate()
    {
        if (!$this->code && $this->name) {
            $this->code = Str::slug($this->name);
        }
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function openShift(): ?Shift
    {
        return $this->shifts()->where('status', 'open')->first();
    }
}
