<?php namespace Aero\Community\Models;

use Model;

class Vote extends Model
{
    public $table = 'aero_community_votes';

    public $fillable = ['post_id', 'user_id', 'tenant_id'];
}
