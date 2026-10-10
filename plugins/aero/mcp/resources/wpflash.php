<?php

/** Aero.WpFlash — vínculos con WordPress. Sin usuario/contraseña del admin de WP. */
return [
    'wpflash_sites' => [
        'plugin' => 'Aero.WpFlash', 'label' => 'sitios WordPress', 'model' => \Aero\WpFlash\Models\SiteInstance::class,
        'fields' => 'id,wp_site_id,wp_admin_url,primary_domain,status,last_synced_at,error_message,created_at', 'filters' => 'status',
    ],
    'wpflash_product_links' => [
        'plugin' => 'Aero.WpFlash', 'label' => 'productos enlazados con WordPress', 'model' => \Aero\WpFlash\Models\ProductLink::class,
        'fields' => 'id,wp_product_id,shop_product_id,sku,wp_updated_at,last_synced_at', 'search' => 'sku', 'filters' => 'shop_product_id',
    ],
    'wpflash_customer_links' => [
        'plugin' => 'Aero.WpFlash', 'label' => 'clientes enlazados con WordPress', 'model' => \Aero\WpFlash\Models\CustomerLink::class,
        'fields' => 'id,wp_customer_id,shop_customer_id,email,last_synced_at', 'search' => 'email',
    ],
];
