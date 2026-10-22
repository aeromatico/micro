<?php namespace Aero\Realestate\Models;

use Aero\Realestate\Classes\CurrentTenant;
use Model;

/**
 * Departamento > ciudad > zona > barrio. tenant_id null = catálogo global de Bolivia
 * (lo ven todos, solo el superadmin lo edita); cada inmobiliaria agrega sus zonas y barrios.
 */
class Location extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Realestate\Classes\TenantOwned;

    public $table = 'aero_realestate_locations';

    public $fillable = ['tenant_id', 'parent_id', 'type', 'name', 'lat', 'lng'];

    public $rules = [
        'name' => 'required|string|max:255',
        'type' => 'required|in:department,city,zone,neighborhood',
    ];

    public $belongsTo = ['parent' => [self::class, 'key' => 'parent_id']];

    public static array $types = [
        'department'   => 'Departamento',
        'city'         => 'Ciudad',
        'zone'         => 'Zona',
        'neighborhood' => 'Barrio',
    ];

    /** Catálogo global + lo propio. Para dropdowns y filtros del tenant. */
    public function scopeAvailable($query)
    {
        $id = CurrentTenant::isAdmin() ? null : CurrentTenant::id();

        return CurrentTenant::isAdmin()
            ? $query
            : $query->where(fn ($q) => $q->whereNull('tenant_id')->when($id, fn ($w) => $w->orWhere('tenant_id', $id)));
    }

    public function getScopeNameAttribute(): string
    {
        return $this->tenant_id ? 'Propia' : 'Global (Bolivia)';
    }

    public function getTypeNameAttribute(): string
    {
        return self::$types[$this->type] ?? $this->type;
    }

    /** Cadena desde el departamento hasta este nivel: [Departamento, Ciudad, Zona…]. */
    public function chain(): array
    {
        $chain = [];
        $node = $this;
        for ($i = 0; $node && $i < 6; $i++) {
            array_unshift($chain, $node);
            $node = $node->parent_id ? static::find($node->parent_id) : null;
        }

        return $chain;
    }

    public function getPathAttribute(): string
    {
        return implode(' › ', array_map(fn ($n) => $n->name, $this->chain()));
    }

    public function getTypeOptions(): array
    {
        return self::$types;
    }

    public function getParentIdOptions(): array
    {
        return static::available()->orderBy('type')->orderBy('name')->get()
            ->mapWithKeys(fn ($l) => [$l->id => $l->path])->all();
    }

    public function beforeSave(): void
    {
        $this->assertReferencesVisible(['parent_id' => self::class]);
    }
}
