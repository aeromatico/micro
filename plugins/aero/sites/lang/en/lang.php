<?php return [

    'plugin' => [
        'name'        => 'Sites',
        'description' => 'Multitenant SaaS for niche microsites',
    ],

    'menu' => [
        'my_site'      => 'My Site',
        'contents'     => 'Contents',
        'settings'     => 'Settings',
        'sites'        => 'Sites',
        'superadmin'   => 'Superadmin',
        'root_domains' => 'Root Domains',
        'tenants'      => 'Tenants',
        'domains'      => 'Domains',
        'pages'        => 'Pages',
        'seo'          => 'SEO',
        'contact'      => 'Contact',
        'channels'     => 'Notification Channels',
        'submissions'  => 'Submissions',
        'api_tokens'   => 'API Tokens',
        'tenant_users' => 'Users & Roles',
    ],

    'permissions' => [
        'superadmin'        => 'Manage tenants and root domains',
        'manage_pages'      => 'Manage pages',
        'manage_seo'        => 'Manage SEO',
        'manage_contact'    => 'Manage contact and channels',
        'view_submissions'  => 'View form submissions',
        'manage_api_tokens' => 'Manage API tokens',
    ],

    'niche' => [
        'generic'         => 'Generic',
        'inmuebles'       => 'Real Estate',
        'consultorio'     => 'Medical Office / Clinic',
        'tienda_whatsapp' => 'WhatsApp Quick Store',
        'radioemisora'    => 'Radio Station',
    ],

    'status' => [
        'active'    => 'Active',
        'inactive'  => 'Inactive',
        'suspended' => 'Suspended',
    ],

    'notification' => [
        'type' => [
            'email'    => 'Email',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'sms'      => 'SMS',
        ],
        'status' => [
            'pending'  => 'Pending',
            'sent'     => 'Sent',
            'failed'   => 'Failed',
            'partial'  => 'Partial',
        ],
    ],

    'tenant' => [
        'credentials_title'   => 'New tenant credentials',
        'credentials_warning' => 'Save these credentials. They will not be shown again.',
        'backend_url'         => 'Backend URL',
        'backend_user'        => 'Admin user',
        'backend_password'    => 'Password',
        'api_token'           => 'Initial API token',
    ],

];
