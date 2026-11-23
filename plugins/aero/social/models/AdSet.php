<?php namespace Aero\Social\Models;

use Model;

/** Conjunto de anuncios (aquí vive el presupuesto si la campaña no lo tiene). */
class AdSet extends Model
{
    public $table = 'aero_social_adsets';

    protected $guarded = ['id'];

    public $belongsTo = [
        'campaign' => [Campaign::class, 'key' => 'campaign_id'],
    ];
}
