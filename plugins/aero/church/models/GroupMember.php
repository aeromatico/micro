<?php namespace Aero\Church\Models;

use Model;

/** Pertenencia de un miembro a un grupo (con rol y fechas de entrada y salida). */
class GroupMember extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public const ROLES = ['leader' => 'Líder', 'assistant' => 'Ayudante', 'member' => 'Integrante'];

    public $table = 'aero_church_group_members';

    public $attributes = ['role' => 'member'];

    public $fillable = ['tenant_id', 'group_id', 'member_id', 'role', 'joined_at', 'left_at'];

    public $rules = [
        'group_id'  => 'required|integer',
        'member_id' => 'required|integer',
        'role'      => 'required|in:leader,assistant,member',
    ];

    public $dates = ['joined_at', 'left_at'];

    public $belongsTo = [
        'group'  => [Group::class, 'key' => 'group_id'],
        'member' => [Member::class, 'key' => 'member_id'],
    ];

    public function beforeValidate(): void
    {
        $this->assertReferencesVisible(['group_id' => Group::class, 'member_id' => Member::class]);
    }
}
