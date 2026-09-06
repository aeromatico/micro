<?php namespace Aero\Chatbots;

use Aero\Chatbots\Classes\ChatbotEngine;
use Aero\Hello\Models\Message;
use Backend;
use Event;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = ['Aero.Sites', 'Aero.Hello'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Chatbots',
            'description' => 'Chatbots de autorespuesta por tenant sobre Aero.Hello',
            'author'      => 'Aero',
            'icon'        => 'icon-android',
            'homepage'    => 'https://micro.clouds.com.bo',
        ];
    }

    public function boot(): void
    {
        Event::listen('aero.hello.messageReceived', function ($message, $payload) {
            ChatbotEngine::handle($message, $payload);
        });

        Message::extend(function ($model) {
            $model->bindEvent('model.afterCreate', function () use ($model) {
                ChatbotEngine::handleOutbound($model);
            });
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.chatbots.manage' => [
                'tab'   => 'Chatbots',
                'label' => 'Gestionar chatbots propios',
            ],
            'aero.chatbots.superadmin' => [
                'tab'   => 'Chatbots',
                'label' => 'Gestionar chatbots de todos los tenants',
            ],
        ];
    }

    public function registerNavigation(): array
    {
        return [
            'chatbots' => [
                'label'       => 'Chatbots',
                'url'         => Backend::url('aero/chatbots/bots'),
                'icon'        => 'icon-android',
                'permissions' => ['aero.chatbots.manage', 'aero.chatbots.superadmin'],
                'order'       => 220,
                'sideMenu'    => [
                    'bots' => [
                        'label'       => 'Bots',
                        'icon'        => 'icon-android',
                        'url'         => Backend::url('aero/chatbots/bots'),
                        'permissions' => ['aero.chatbots.manage', 'aero.chatbots.superadmin'],
                    ],
                    'logs' => [
                        'label'       => 'Registro de respuestas',
                        'icon'        => 'icon-list-alt',
                        'url'         => Backend::url('aero/chatbots/logs'),
                        'permissions' => ['aero.chatbots.manage', 'aero.chatbots.superadmin'],
                    ],
                ],
            ],
        ];
    }
}
