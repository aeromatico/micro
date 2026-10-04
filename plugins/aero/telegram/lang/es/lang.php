<?php return [
    'plugin' => [
        'name'        => 'Telegram',
        'description' => 'Chats privados con bots de Telegram como canal del omnichat (Bot API directa)',
    ],
    'menu' => [
        'telegram' => 'Telegram',
        'bots'     => 'Bots conectados',
    ],
    'permissions' => [
        'manage' => 'Conectar y administrar bots de Telegram',
    ],
    'bots' => [
        'title'          => 'Bots de Telegram',
        'connect_title'  => 'Conectar un bot',
        'connect_help'   => 'Crea el bot con @BotFather en Telegram, copia su token y pégalo aquí. Los clientes deben abrir el chat con el bot para poder escribirse.',
        'token'          => 'Token del bot',
        'token_required' => 'Ingresa el token del bot.',
        'token_invalid'  => 'Telegram rechazó el token: :error',
        'tenant'         => 'Tenant',
        'tenant_none'    => 'Plataforma (sin tenant)',
        'connect'        => 'Conectar bot',
        'connected'      => 'Bot :name conectado y webhook registrado.',
        'reconfigure'    => 'Reconfigurar webhook',
        'reconfigured'   => 'Webhook registrado de nuevo.',
        'list_title'     => 'Bots conectados',
        'empty'          => 'Todavía no hay bots de Telegram conectados.',
        'name'           => 'Nombre',
        'username'       => 'Usuario',
        'status'         => 'Estado',
    ],
];
