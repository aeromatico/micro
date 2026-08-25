<?php namespace Aero\Crm\Models;

use Model;

class TeamMember extends Model
{
    public $table = 'aero_crm_team_members';

    public $fillable = ['team_id', 'user_id'];

    public $belongsTo = [
        'team' => [Team::class],
        'user' => [\Backend\Models\User::class],
    ];

    public $timestamps = true;
}
