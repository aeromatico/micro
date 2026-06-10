<?php return [

    'plugin' => [
        'name'        => 'Sites',
        'description' => 'SaaS multitenant para micrositios web por nichos',
    ],

    'menu' => [
        'my_site'      => 'Mi Sitio',
        'contents'     => 'Contenidos',
        'settings'     => 'Configuración',
        'sites'        => 'Sites',
        'superadmin'   => 'Superadmin',
        'root_domains' => 'Dominios Raíz',
        'tenants'      => 'Tenants',
        'domains'      => 'Dominios',
        'pages'        => 'Páginas',
        'seo'          => 'SEO',
        'contact'      => 'Contacto',
        'channels'     => 'Canales de Notificación',
        'submissions'  => 'Envíos',
        'api_tokens'   => 'Tokens API',
        'tenant_users' => 'Usuarios & Roles',
    ],

    'permissions' => [
        'superadmin'        => 'Gestionar tenants y dominios raíz',
        'manage_pages'      => 'Gestionar páginas',
        'manage_seo'        => 'Gestionar SEO',
        'manage_contact'    => 'Gestionar contacto y canales',
        'view_submissions'  => 'Ver envíos del formulario',
        'manage_api_tokens' => 'Gestionar tokens API',
    ],

    'niche' => [
        'generic'         => 'Genérico',
        'inmuebles'       => 'Inmuebles / Bienes Raíces',
        'consultorio'     => 'Consultorio / Clínica',
        'tienda_whatsapp' => 'Tienda Rápida WhatsApp',
        'radioemisora'    => 'Radio Emisora',
    ],

    'status' => [
        'active'    => 'Activo',
        'inactive'  => 'Inactivo',
        'suspended' => 'Suspendido',
    ],

    'notification' => [
        'type' => [
            'email'    => 'Email',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'sms'      => 'SMS',
        ],
        'status' => [
            'pending'  => 'Pendiente',
            'sent'     => 'Enviado',
            'failed'   => 'Fallido',
            'partial'  => 'Parcial',
        ],
    ],

    'tenant' => [
        'credentials_title'   => 'Credenciales del tenant creado',
        'credentials_warning' => 'Guarda estas credenciales. No se volverán a mostrar.',
        'backend_url'         => 'URL del backend',
        'backend_user'        => 'Usuario admin',
        'backend_password'    => 'Contraseña',
        'api_token'           => 'Token API inicial',
    ],

];
