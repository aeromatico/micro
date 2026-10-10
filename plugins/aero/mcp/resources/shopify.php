<?php

/** Aero.Shopify — tiendas y pedidos. Sin access_token / client_secret / token de pedido. */
return [
    'shopify_stores' => [
        'plugin' => 'Aero.Shopify', 'label' => 'tiendas Shopify', 'model' => \Aero\Shopify\Models\Store::class,
        'fields' => 'id,uuid,name,shop_domain,bank_account_id,is_active,last_webhook_at,last_error,created_at', 'filters' => 'is_active',
    ],
    'shopify_orders' => [
        'plugin' => 'Aero.Shopify', 'label' => 'pedidos de Shopify', 'model' => \Aero\Shopify\Models\Order::class,
        'fields' => 'id,store_id,shopify_order_id,order_name,customer_email,amount,currency,qr_code_id,status,error,paid_synced_at,created_at',
        'search' => 'order_name,customer_email', 'filters' => 'store_id,status',
    ],
];
