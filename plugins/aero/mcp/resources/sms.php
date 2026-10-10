<?php

/** Aero.Sms — solo lectura (el envío cobra créditos y pasa por el servicio de SMS). */
return [
    'sms_messages' => [
        'plugin' => 'Aero.Sms', 'label' => 'SMS enviados', 'model' => \Aero\Sms\Models\Message::class,
        'fields' => 'id,batch_id,consumer,reference,to,body,segments,encoding,status,driver,error_code,error_message,credits_charged,scheduled_at,sent_at,delivered_at,created_at',
        'search' => 'to,reference,body', 'filters' => 'batch_id,status,driver,consumer',
    ],
    'sms_batches' => [
        'plugin' => 'Aero.Sms', 'label' => 'lotes de SMS', 'model' => \Aero\Sms\Models\Batch::class,
        'fields' => 'id,name,consumer,status,total,credits_charged,scheduled_at,completed_at,created_at', 'search' => 'name', 'filters' => 'status,consumer',
    ],
    'sms_templates' => [
        'plugin' => 'Aero.Sms', 'label' => 'plantillas de SMS', 'model' => \Aero\Sms\Models\Template::class,
        'fields' => 'id,name,slug,body,is_active', 'search' => 'name,body', 'filters' => 'is_active',
    ],
];
