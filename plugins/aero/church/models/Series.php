<?php namespace Aero\Church\Models;

use Model;

/** Serie de enseñanzas (agrupa predicaciones). */
class Series extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \Aero\Church\Classes\TenantOwned;

    public $table = 'aero_church_series';

    public $fillable = ['tenant_id', 'name', 'description'];

    public $rules = ['name' => 'required|string|max:255'];

    public $hasMany = [
        'sermons' => [Sermon::class, 'key' => 'series_id'],
    ];
}
