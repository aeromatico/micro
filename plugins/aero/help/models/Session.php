<?php namespace Aero\Help\Models;

use Model;

/** Una conversación de ayuda de una persona del panel. Solo la ve su autora. */
class Session extends Model
{
    public $table = 'aero_help_sessions';

    public $fillable = [
        'tenant_id', 'user_id', 'flow_id', 'topic_id', 'area', 'url', 'status', 'state', 'transcript',
        'escalated_via', 'escalated_ref', 'resolved_at',
    ];

    public $jsonable = ['state', 'transcript'];

    protected $dates = ['resolved_at'];

    public $belongsTo = [
        'flow' => [Flow::class, 'key' => 'flow_id'],
    ];

    /** Añade una línea al historial ('u' persona, 'b' ayuda), acotado para que no crezca sin límite. */
    public function remember(string $who, string $text): void
    {
        $log = (array) $this->transcript;
        $log[] = ['r' => $who, 't' => mb_substr(trim($text), 0, 500)];
        $this->transcript = array_slice($log, -60);
    }
}
