<?php namespace Aero\Chatbots\Models;

use Model;

/**
 * Estado de handoff por conversación: mientras `paused_until` esté en el
 * futuro, el bot no responde porque un humano tomó la conversación.
 */
class ConversationState extends Model
{
    public $table = 'aero_chatbots_conversation_states';

    public $timestamps = true;

    public $fillable = ['bot_id', 'conversation_id', 'paused_until'];

    protected $dates = ['paused_until'];

    public $belongsTo = [
        'bot'          => [Bot::class],
        'conversation' => [\Aero\Hello\Models\Conversation::class],
    ];

    public function isPaused(): bool
    {
        return $this->paused_until !== null && $this->paused_until->isFuture();
    }
}
