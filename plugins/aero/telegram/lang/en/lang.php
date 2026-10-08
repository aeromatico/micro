<?php return [
    'plugin' => [
        'name'        => 'Telegram',
        'description' => 'Telegram bot private chats as an omnichat channel (direct Bot API)',
    ],
    'menu' => [
        'telegram' => 'Telegram',
        'bots'     => 'Connected bots',
    ],
    'permissions' => [
        'manage' => 'Connect and manage Telegram bots',
    ],
    'bots' => [
        'title'          => 'Telegram bots',
        'connect_title'  => 'Connect a bot',
        'connect_help'   => 'Create the bot with @BotFather on Telegram, copy its token and paste it here. Customers must open a chat with the bot before it can message them.',
        'token'          => 'Bot token',
        'token_required' => 'Enter the bot token.',
        'token_invalid'  => 'Telegram rejected the token: :error',
        'tenant'         => 'Tenant',
        'tenant_none'    => 'Platform (no tenant)',
        'connect'        => 'Connect bot',
        'connected'      => 'Bot :name connected and webhook registered.',
        'reconfigure'    => 'Reconfigure webhook',
        'reconfigured'   => 'Webhook registered again.',
        'list_title'     => 'Connected bots',
        'empty'          => 'No Telegram bots connected yet.',
        'name'           => 'Name',
        'username'       => 'Username',
        'status'         => 'Status',
    ],
];
