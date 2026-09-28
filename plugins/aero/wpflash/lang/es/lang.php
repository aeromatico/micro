<?php return [
    'plugin' => [
        'name'        => 'WordPress Flash',
        'description' => 'Motor de sitio alternativo: un childsite de WordPress/WooCommerce administrado por nosotros, sincronizado en un solo sentido (WP manda) con Aero.Shop.',
    ],
    'menu' => [
        'wpflash' => 'WordPress Flash',
    ],
    'permissions' => [
        'manage_sites' => 'Gestionar sitios WordPress Flash',
    ],
    'settings' => [
        'description' => 'Conector de red, conector de Cloudflare y host de destino del WordPress Multisite.',
    ],
    'types' => [
        'woocommerce' => 'WooCommerce (WordPress Flash)',
        'cloudflare'  => 'Cloudflare (DNS)',
    ],
    'site' => [
        'status_provisioning' => 'Creando',
        'status_active'       => 'Activo',
        'status_error'        => 'Error',
        'status_suspended'    => 'Suspendido',
    ],
];
