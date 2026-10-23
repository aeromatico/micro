<?php namespace Aero\Church\Models;

use Model;

/** Reunión de un grupo. La asistencia cuelga de ella. */
class Meeting extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_meetings';

    public $fillable = ['tenant_id', 'group_id', 'title', 'meeting_date', 'start_time', 'location', 'notes'];

    public $rules = [
        'group_id'     => 'required|integer',
        'meeting_date' => 'required|date',
    ];

    public $dates = ['meeting_date'];

    public $belongsTo = [
        'group' => [Group::class, 'key' => 'group_id'],
    ];

    public $hasMany = [
        'attendances' => [Attendance::class, 'key' => 'meeting_id'],
    ];

    public function beforeValidate(): void
    {
        // El grupo debe ser de la iglesia y, para un líder, uno de los suyos.
        if (\BackendAuth::getUser() && $this->group_id && !Group::managed()->whereKey($this->group_id)->exists()) {
            throw new \ApplicationException('El grupo no es válido.');
        }
    }

    /** Alcance del usuario del panel: reuniones de los grupos que puede gestionar. */
    public function scopeManaged($query)
    {
        return $query->whereIn($this->getTable() . '.group_id', Group::managed()->select('aero_church_groups.id'));
    }

    public function getGroupIdOptions(): array
    {
        return Group::managed()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Si el grupo es de otra iglesia, la reunión hereda el tenant del grupo. */
    public function beforeCreate(): void
    {
        if ($this->group_id && ($g = Group::find($this->group_id))) {
            $this->tenant_id = $g->tenant_id;
        }
    }
}
