<?php return [
    'plugin' => [
        'name'        => 'wapi',
        'description' => 'WhatsApp Web (own QR session) driver for Aero.Hello',
    ],
    'menu' => [
        'wapi'      => 'wapi',
        'instances' => 'Connected numbers',
    ],
    'profile' => [
        'api_key_comment' => 'Overrides the global wapi API key just for accounts connected under this profile.',
    ],
    'settings' => [
        'label'                          => 'wapi — WhatsApp Web',
        'description'                    => 'Credentials for your self-hosted wapi instance (WhatsApp Web)',
        'base_url'                       => 'API base URL',
        'base_url_comment'               => 'Base of the wapi REST API, without a trailing /v1. Defaults to https://wapi.clouds.com.bo/v1.',
        'api_key'                        => 'API key',
        'api_key_comment'                => 'Generated with `npm run cli:init` on the wapi server. Stored in plaintext in system_settings — see Aero.Hello\'s SettingsModel note.',
        'webhook_secret'                 => 'Webhook secret',
        'webhook_secret_comment'         => 'The same `secret` you gave wapi when creating the webhook (POST /v1/webhooks). Verifies X-Webhook-Signature.',
        'allow_unsigned_webhooks'        => 'Accept unsigned webhooks',
        'allow_unsigned_webhooks_comment' => 'Testing only: without a webhook secret, anyone who knows the URL can inject fake messages.',
    ],
];
