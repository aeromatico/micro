<?php return [
    'plugin' => [
        'name'        => 'WordPress Flash',
        'description' => 'Alternative site engine: a WordPress/WooCommerce childsite we manage, synced one-way (WP wins) with Aero.Shop.',
    ],
    'menu' => [
        'wpflash' => 'WordPress Flash',
    ],
    'permissions' => [
        'manage_sites' => 'Manage WordPress Flash sites',
    ],
    'settings' => [
        'description' => 'Network connector, Cloudflare connector and WordPress Multisite target host.',
    ],
    'types' => [
        'woocommerce' => 'WooCommerce (WordPress Flash)',
        'cloudflare'  => 'Cloudflare (DNS)',
    ],
    'site' => [
        'status_provisioning' => 'Provisioning',
        'status_active'       => 'Active',
        'status_error'        => 'Error',
        'status_suspended'    => 'Suspended',
    ],
];
