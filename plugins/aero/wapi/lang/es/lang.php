<?php return [
    'plugin' => [
        'name'        => 'wapi',
        'description' => 'Driver de WhatsApp Web (sesión propia por QR) para Aero.Hello',
    ],
    'menu' => [
        'wapi'      => 'wapi',
        'instances' => 'Números conectados',
    ],
    'profile' => [
        'api_key_comment' => 'Sobreescribe la API key global de wapi solo para las cuentas conectadas bajo este perfil.',
    ],
    'settings' => [
        'label'                          => 'wapi — WhatsApp Web',
        'description'                    => 'Credenciales de la instancia propia de wapi (WhatsApp Web self-hosted)',
        'base_url'                       => 'URL base de la API',
        'base_url_comment'               => 'Base de la REST API de wapi, sin /v1 al final. Por defecto https://wapi.clouds.com.bo/v1.',
        'api_key'                        => 'API key',
        'api_key_comment'                => 'Generada con `npm run cli:init` en el servidor de wapi. En texto plano en system_settings — ver la nota de Aero.Hello sobre SettingsModel.',
        'webhook_secret'                 => 'Webhook secret',
        'webhook_secret_comment'         => 'El mismo `secret` que le diste a wapi al crear el webhook (POST /v1/webhooks). Verifica X-Webhook-Signature.',
        'allow_unsigned_webhooks'        => 'Aceptar webhooks sin firma',
        'allow_unsigned_webhooks_comment' => 'Solo para pruebas: sin webhook secret configurado, cualquiera que conozca la URL puede inyectar mensajes falsos.',
    ],
];
