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
        'description' => 'Rutas y binarios de WP-CLI para el WordPress Multisite de este mismo servidor.',
    ],
    'types' => [
        'woocommerce' => 'WooCommerce (WordPress Flash)',
    ],
    'site' => [
        'status_provisioning' => 'Creando',
        'status_active'       => 'Activo',
        'status_error'        => 'Error',
        'status_suspended'    => 'Suspendido',
    ],
];
