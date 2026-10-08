<?php namespace Aero\Telegram;

use Backend;
use Event;
use Route;
use System\Classes\PluginBase;

/**
 * Canal de Telegram (chats privados con un bot) para Aero.Hello. Zernio no
 * entrega mensajes privados de Telegram, así que el bot habla directo con la
 * Bot API. Se registra en MessageDispatcher bajo el handle 'telegram'; el
 * pipeline de envío/recepción de Hello no necesita saber que existe.
 */
class Plugin extends PluginBase
{
    public $require = ['Aero.Hello'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'aero.telegram::lang.plugin.name',
            'description' => 'aero.telegram::lang.plugin.description',
            'author'      => 'Aero',
            'icon'        => 'icon-paper-plane',
        ];
    }

    public function boot(): void
    {
        Event::listen('aero.hello.registerChannelDrivers', function ($dispatcher) {
            $dispatcher->register('telegram', \Aero\Telegram\Classes\Notifications\TelegramChannelDriver::class);
        });

        Route::post('api/v1/telegram/webhooks/{accountId}', [
            \Aero\Telegram\Http\Controllers\Api\TelegramWebhookController::class, 'handle',
        ])->middleware('throttle:300,1');
    }

    public function registerPermissions(): array
    {
        return [
            'aero.telegram.manage' => [
                'tab'   => 'Telegram',
                'label' => 'aero.telegram::lang.permissions.manage',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'telegram' => [
                'label'       => 'aero.telegram::lang.menu.telegram',
                'url'         => Backend::url('aero/telegram/bots'),
                'icon'        => 'icon-paper-plane',
                'permissions' => ['aero.telegram.manage'],
                'order'       => 212,
                'sideMenu'    => [
                    'bots' => [
                        'label'       => 'aero.telegram::lang.menu.bots',
                        'icon'        => 'icon-paper-plane',
                        'url'         => Backend::url('aero/telegram/bots'),
                        'permissions' => ['aero.telegram.manage'],
                    ],
                ],
            ],
        ];
    }
}
