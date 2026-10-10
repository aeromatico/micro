<?php

/** Aero.Services — solicitudes de servicios del tenant. */
return [
    'services_purchases' => [
        'plugin' => 'Aero.Services', 'label' => 'compras de servicios', 'model' => \Aero\Services\Models\ServicePurchase::class,
        'fields' => 'id,service_id,plan_index,payment_method,credit_type_code,amount,status,requested_by,fulfilled_by,fulfilled_at,cancelled_at,notes,created_at',
        'filters' => 'service_id,status,payment_method',
    ],
];
