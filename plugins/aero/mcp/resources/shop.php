<?php

/**
 * Aero.Shop — solo lectura. Pedidos, stock y precios tienen reglas (OrderService,
 * movimientos de stock, créditos): se habilitarán por tools de servicio, no por escritura directa.
 */
return [
    'shop_products' => [
        'plugin' => 'Aero.Shop', 'label' => 'productos de la tienda', 'model' => \Aero\Shop\Models\Product::class,
        'fields' => 'id,collection_id,type,name,slug,description,sku,has_variants,base_price,compare_at_price,cost_price,weight_grams,requires_shipping,track_inventory,stock_quantity,allow_backorder,status,is_featured,published_at,prep_minutes,min_quantity,barcode,is_internal,created_at',
        'search' => 'name,sku,barcode', 'filters' => 'collection_id,status,type,is_featured',
    ],
    'shop_product_variants' => [
        'plugin' => 'Aero.Shop', 'label' => 'variantes de producto', 'model' => \Aero\Shop\Models\ProductVariant::class,
        'fields' => 'id,product_id,sku,price,compare_at_price,cost_price,stock_quantity,weight_grams,is_active,position',
        'search' => 'sku', 'filters' => 'product_id,is_active',
    ],
    'shop_product_options' => [
        'plugin' => 'Aero.Shop', 'label' => 'opciones de producto', 'model' => \Aero\Shop\Models\ProductOption::class,
        'fields' => 'id,product_id,name,sort_order', 'filters' => 'product_id',
    ],
    'shop_product_option_values' => [
        'plugin' => 'Aero.Shop', 'label' => 'valores de opciones de producto', 'model' => \Aero\Shop\Models\ProductOptionValue::class,
        'fields' => 'id,product_option_id,value,sort_order', 'filters' => 'product_option_id',
    ],
    'shop_modifier_groups' => [
        'plugin' => 'Aero.Shop', 'label' => 'grupos de modificadores', 'model' => \Aero\Shop\Models\ModifierGroup::class,
        'fields' => 'id,product_id,name,min_select,max_select,choices,sort_order', 'filters' => 'product_id',
    ],
    'shop_collections' => [
        'plugin' => 'Aero.Shop', 'label' => 'colecciones de la tienda', 'model' => \Aero\Shop\Models\Collection::class,
        'fields' => 'id,parent_id,name,slug,description,is_active,sort_order', 'search' => 'name', 'filters' => 'parent_id,is_active',
    ],
    'shop_customers' => [
        'plugin' => 'Aero.Shop', 'label' => 'clientes de la tienda', 'model' => \Aero\Shop\Models\Customer::class,
        'fields' => 'id,email,first_name,last_name,phone,is_guest,tax_id,tax_name,created_at',
        'search' => 'first_name,last_name,email,phone,tax_id', 'filters' => 'is_guest',
    ],
    'shop_addresses' => [
        'plugin' => 'Aero.Shop', 'label' => 'direcciones de clientes', 'model' => \Aero\Shop\Models\Address::class,
        'fields' => 'id,customer_id,type,full_name,phone,address_line1,address_line2,city,state_province,postal_code,country_code,is_default',
        'filters' => 'customer_id,type,city',
    ],
    'shop_orders' => [
        'plugin' => 'Aero.Shop', 'label' => 'pedidos de la tienda', 'model' => \Aero\Shop\Models\Order::class,
        'fields' => 'id,customer_id,order_number,status,subtotal,discount_total,shipping_total,tax_total,grand_total,payment_reference,paid_at,fulfilled_at,cancelled_at,cancel_reason,notes,customer_notes,requires_shipping,order_type,table_label,scheduled_for,kitchen_status,source,branch_name,created_at',
        'search' => 'order_number,payment_reference', 'filters' => 'status,customer_id,order_type,kitchen_status,source',
    ],
    'shop_order_items' => [
        'plugin' => 'Aero.Shop', 'label' => 'líneas de pedido', 'model' => \Aero\Shop\Models\OrderItem::class,
        'fields' => 'id,order_id,product_id,product_variant_id,product_name_snapshot,variant_label_snapshot,sku_snapshot,unit_price,quantity,line_total,note,created_at',
        'filters' => 'order_id,product_id',
    ],
    'shop_order_status_history' => [
        'plugin' => 'Aero.Shop', 'label' => 'historial de estados de pedido', 'model' => \Aero\Shop\Models\OrderStatusHistory::class,
        'tenant_via' => ['order_id', \Aero\Shop\Models\Order::class],
        'fields' => 'id,order_id,from_status,to_status,note,created_at', 'filters' => 'order_id',
    ],
    'shop_stock_movements' => [
        'plugin' => 'Aero.Shop', 'label' => 'movimientos de stock', 'model' => \Aero\Shop\Models\StockMovement::class,
        'fields' => 'id,product_id,product_variant_id,type,quantity_delta,quantity_after,order_id,note,created_at',
        'filters' => 'product_id,product_variant_id,type,order_id',
    ],
];
