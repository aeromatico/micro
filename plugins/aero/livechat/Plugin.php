<?php namespace Aero\Livechat;

use Backend;
use Event;
use System\Classes\PluginBase;

/**
 * Chat en vivo para visitantes de un sitio web: widget embebible (script
 * público) + bandeja de agentes en el backend. Independiente de aero/chat
 * (que es el inbox multiagente de WhatsApp) y de aero/crm — fase 1 aislada,
 * sin integración con esos plugins todavía.
 */
class Plugin extends PluginBase
{
    public $require = ['Aero.Sites', 'Aero.Connector', 'Aero.Hello'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Livechat',
            'description' => 'Chat en vivo embebible para sitios web: widget público + bandeja de agentes, multi-tenant.',
            'author'      => 'Aero',
            'icon'        => 'icon-comment-o',
        ];
    }

    public function register(): void
    {
        $this->registerConsoleCommand('livechat:auto-finish', \Aero\Livechat\Console\AutoFinishInactiveConversations::class);
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('livechat:auto-finish')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->name('aero-livechat-auto-finish');
    }

    public function boot(): void
    {
        $this->app['router']->group([], function () {
            require __DIR__ . '/routes.php';
        });

        $this->bootTenantPurgeCleanup();
        $this->bootTelegramBridge();
        $this->bootHelloBridge();
    }

    /**
     * Cada inbox aparece como una cuenta más del PWA de aero/chat (tema
     * whatsapp) — ver Classes\HelloBridge y Classes\Notifications\LivechatChannelDriver.
     */
    protected function bootHelloBridge(): void
    {
        Event::listen('aero.hello.registerChannelDrivers', function ($dispatcher) {
            $dispatcher->register('livechat', \Aero\Livechat\Classes\Notifications\LivechatChannelDriver::class);
        });
    }

    /**
     * Un solo WebhookEndpoint ("livechat-telegram", ver
     * seed_telegram_webhook_endpoint.php) recibe las entregas de TODOS los
     * bots/inboxes — TelegramBridge resuelve a qué inbox pertenece por
     * chat_id, no por el endpoint.
     */
    protected function bootTelegramBridge(): void
    {
        Event::listen('aero.livechat.telegram_inbound', function ($endpoint, array $payload, $request) {
            \Aero\Livechat\Classes\TelegramBridge::handleInbound($payload);
        });
    }

    /**
     * Al purgar un tenant (Aero\Sites\Models\Tenant::purge()), borra en
     * cascada sus inboxes/contactos/conversaciones/mensajes.
     */
    protected function bootTenantPurgeCleanup(): void
    {
        Event::listen('aero.sites.tenant.purging', function ($tenant) {
            $tenantId = $tenant->id;

            \Aero\Livechat\Models\Message::whereIn('conversation_id', function ($q) use ($tenantId) {
                $q->select('id')->from('aero_livechat_conversations')->where('tenant_id', $tenantId);
            })->delete();

            \Aero\Livechat\Models\Conversation::where('tenant_id', $tenantId)->delete();
            \Aero\Livechat\Models\Contact::where('tenant_id', $tenantId)->delete();

            // Cuentas espejo en Aero.Hello (ver HelloBridge::account): sin esto
            // quedan huérfanas apuntando a un tenant que ya no existe.
            \Aero\Hello\Models\Account::where('driver', 'livechat')
                ->whereIn('zernio_account_id', \Aero\Livechat\Models\Inbox::where('tenant_id', $tenantId)->pluck('id')->map(fn ($id) => "livechat-inbox-{$id}"))
                ->delete();

            \Aero\Livechat\Models\Inbox::where('tenant_id', $tenantId)->delete();
        });
    }

    public function registerNavigation(): array
    {
        return [
            'livechat' => [
                'label'       => 'Livechat',
                'url'         => Backend::url('aero/livechat/conversations'),
                'icon'        => 'icon-comment-o',
                'permissions' => ['aero.livechat.manage_conversations', 'aero.livechat.manage_inboxes'],
                'order'       => 170,
                'sideMenu'    => [
                    'livechat-conversations' => [
                        'label'       => 'Bandeja',
                        'icon'        => 'icon-comments',
                        'url'         => Backend::url('aero/livechat/conversations'),
                        'permissions' => ['aero.livechat.manage_conversations'],
                    ],
                    'livechat-inboxes' => [
                        'label'       => 'Inboxes',
                        'icon'        => 'icon-inbox',
                        'url'         => Backend::url('aero/livechat/inboxes'),
                        'permissions' => ['aero.livechat.manage_inboxes'],
                    ],
                ],
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.livechat.manage_inboxes' => [
                'tab'   => 'Livechat',
                'label' => 'Gestionar inboxes',
            ],
            'aero.livechat.manage_conversations' => [
                'tab'   => 'Livechat',
                'label' => 'Gestionar conversaciones',
            ],
        ];
    }
}
