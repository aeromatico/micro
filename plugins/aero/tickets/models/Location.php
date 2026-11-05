<?php namespace Aero\Tickets\Models;

use Aero\Tickets\Classes\Audit as AuditLog;
use Model;

/** Departamentos y ciudades que ofrece el formulario de eventos. */
class Location extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_tickets_locations';
    public $fillable = ['type', 'name', 'parent_id', 'is_active', 'sort_order'];
    public $rules = ['type' => 'required|in:department,city', 'name' => 'required|string|max:100'];

    public $attributes = ['is_active' => true, 'sort_order' => 0];

    public $belongsTo = ['department' => [self::class, 'key' => 'parent_id']];

    public function getTypeOptions(): array { return ['department' => 'Departamento', 'city' => 'Ciudad']; }
    public function getParentIdOptions(): array { return static::where('type', 'department')->orderBy('sort_order')->pluck('name', 'id')->all(); }

    public function scopeDepartments($q) { return $q->where('type', 'department')->where('is_active', true)->orderBy('sort_order')->orderBy('name'); }
    public function scopeCitiesOf($q, $departmentName) { return $q->where('type', 'city')->where('is_active', true)->whereIn('parent_id', static::where('type', 'department')->where('name', $departmentName)->pluck('id'))->orderBy('sort_order')->orderBy('name'); }

    public function beforeSave(): void
    {
        if ($this->type === 'department') {
            $this->parent_id = null;
        } elseif (!$this->parent_id) {
            throw new \ApplicationException('Una ciudad necesita su departamento.');
        }
        if ($this->parent_id === '') $this->parent_id = null;
    }

    public function beforeDelete(): void
    {
        if ($this->type === 'department' && static::where('parent_id', $this->id)->exists()) {
            throw new \ApplicationException('Este departamento tiene ciudades: desactívalo o elimina primero sus ciudades.');
        }
        if ($this->type === 'department' ? Event::where('department', $this->name)->exists() : Event::where('city', $this->name)->exists()) {
            throw new \ApplicationException('Hay eventos en esta ubicación: desactívala en lugar de eliminarla.');
        }
    }

    public function afterSave(): void
    {
        AuditLog::record($this->wasRecentlyCreated ? 'location.created' : 'location.updated', 'location', $this->id, ['note' => $this->name, 'changes' => AuditLog::diff($this->getOriginal(), $this->getAttributes(), ['name', 'is_active', 'parent_id'])]);
    }
}
