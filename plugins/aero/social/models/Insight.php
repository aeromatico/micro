<?php namespace Aero\Social\Models;

use Model;

/** Métricas diarias de una campaña (gasto en la moneda de la cuenta). */
class Insight extends Model
{
    public $table = 'aero_social_insights';

    protected $guarded = ['id'];


    public $belongsTo = [
        'campaign' => [Campaign::class, 'key' => 'campaign_id'],
    ];
}
