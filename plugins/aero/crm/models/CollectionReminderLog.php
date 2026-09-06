<?php namespace Aero\Crm\Models;

use Model;

/**
 * Registra que una CollectionReminderRule ya se disparó para un
 * CollectionItem puntual (unique por par), para que el generador diario no
 * reenvíe el mismo paso de la cascada más de una vez.
 */
class CollectionReminderLog extends Model
{
    public $table = 'aero_crm_collection_reminder_logs';

    public $fillable = ['collection_item_id', 'collection_reminder_rule_id', 'scheduled_date', 'sent_at'];

    protected $dates = ['scheduled_date', 'sent_at'];

    public $belongsTo = [
        'collectionItem' => [CollectionItem::class],
        'rule'            => [CollectionReminderRule::class],
    ];
}
