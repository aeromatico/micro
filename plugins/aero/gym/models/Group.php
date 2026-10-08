<?php namespace Aero\Gym\Models;

use Aero\Gym\Classes\TenantOwned;
use Model;

class Group extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use TenantOwned;

    public $table = 'aero_gym_groups';

    public $fillable = ['tenant_id', 'name', 'purpose', 'description', 'is_active'];

    public $rules = ['name' => 'required|string|max:255'];

    public function getMembersOptions(): array
    {
        return Member::visible()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Fuera del grupo quien sea de otro tenant (ids manipulados). */
    public function afterSave(): void
    {
        $foreign = $this->members()->where('aero_gym_members.tenant_id', '!=', $this->tenant_id)->pluck('aero_gym_members.id')->all();
        if ($foreign) {
            $this->members()->detach($foreign);
        }
    }

    public $belongsToMany = [
        'members' => [Member::class, 'table' => 'aero_gym_group_member', 'key' => 'group_id', 'otherKey' => 'member_id', 'scope' => 'visible'],
    ];
}
