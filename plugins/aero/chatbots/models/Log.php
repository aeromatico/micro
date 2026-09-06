<?php namespace Aero\Chatbots\Models;

use Model;

/**
 * Registro de cada disparo del bot: qué regla matcheó (o null si fue el
 * fallback) y qué mensajes de entrada/salida de Aero.Hello involucró. Es de
 * solo lectura desde el backend, sirve para que el tenant audite qué está
 * contestando su bot.
 */
class Log extends Model
{
    public $table = 'aero_chatbots_logs';

    public $timestamps = true;

    public $fillable = [
        'bot_id', 'conversation_id', 'inbound_message_id', 'outbound_message_id', 'rule_id', 'matched_at',
    ];

    protected $dates = ['matched_at'];

    public $belongsTo = [
        'bot'  => [Bot::class],
        'rule' => [Rule::class],
    ];
}
