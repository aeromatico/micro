<?php namespace Aero\Realestate\Models;

use Model;

/** Cambio de precio o de disponibilidad de una propiedad publicada; el comando de alertas lo avisa a quienes la guardaron. */
class Change extends Model
{
    public $table = 'aero_realestate_changes';

    public $timestamps = false;

    public $fillable = ['tenant_id', 'property_id', 'offer_id', 'kind', 'old_value', 'new_value', 'currency', 'created_at'];

    public $dates = ['created_at', 'notified_at'];

    public $belongsTo = ['property' => [Property::class, 'key' => 'property_id']];
}
