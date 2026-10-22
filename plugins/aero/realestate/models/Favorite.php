<?php namespace Aero\Realestate\Models;

use Model;

/** Propiedad guardada por un usuario con sesión (los visitantes anónimos guardan en su navegador). */
class Favorite extends Model
{
    public $table = 'aero_realestate_favorites';

    public $timestamps = false;

    public $fillable = ['tenant_id', 'user_id', 'property_id', 'created_at'];

    public $dates = ['created_at'];

    public $belongsTo = ['property' => [Property::class, 'key' => 'property_id']];
}
