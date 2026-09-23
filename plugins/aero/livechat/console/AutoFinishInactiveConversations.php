<?php namespace Aero\Livechat\Console;

use Aero\Livechat\Classes\ConversationLifecycle;
use Aero\Livechat\Models\Conversation;
use Illuminate\Console\Command;

/**
 * livechat:auto-finish — cierra solas las conversaciones abiertas sin
 * actividad (de ningún lado) en los últimos 15 minutos. Corre cada 5
 * minutos (ver Plugin::registerSchedule).
 */
class AutoFinishInactiveConversations extends Command
{
    public const INACTIVITY_MINUTES = 15;

    protected $signature = 'livechat:auto-finish';

    protected $description = 'Finaliza automáticamente las conversaciones de Livechat sin actividad reciente.';

    public function handle(): int
    {
        $stale = Conversation::where('status', Conversation::OPEN)
            ->where(fn ($q) => $q
                ->where('last_message_at', '<', now()->subMinutes(self::INACTIVITY_MINUTES))
                ->orWhere(fn ($w) => $w->whereNull('last_message_at')->where('created_at', '<', now()->subMinutes(self::INACTIVITY_MINUTES))))
            ->get();

        foreach ($stale as $conversation) {
            ConversationLifecycle::finish($conversation, 'Se cerró automáticamente por inactividad.');
        }

        $this->info("Conversaciones cerradas por inactividad: {$stale->count()}.");

        return self::SUCCESS;
    }
}
