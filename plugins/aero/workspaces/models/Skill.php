<?php namespace Aero\Workspaces\Models;

use Model;

/**
 * Skill en formato Agent Skills: `description` ("úsala cuando…") y `body`
 * (SKILL.md). Las oficiales y de hub tienen tenant_id nulo; las personales, el del tenant.
 */
class Skill extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_workspaces_skills';

    public $fillable = ['tenant_id', 'kind', 'name', 'slug', 'description', 'body', 'tools', 'color'];

    public $jsonable = ['tools'];

    public const KINDS = ['official', 'hub', 'personal'];

    public $rules = [
        'kind'        => 'required|in:official,hub,personal',
        'name'        => 'required|max:120',
        'slug'        => 'required|alpha_dash|max:120',
        'description' => 'required|max:1000',
    ];

    public $belongsToMany = [
        'staff' => [
            Staff::class,
            'table' => 'aero_workspaces_staff_skill',
            'key' => 'skill_id',
            'otherKey' => 'staff_id',
        ],
    ];
}
