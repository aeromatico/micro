<?php

/** Aero.Notify — reglas, plantillas y entregas del tenant. Sin direcciones de destino, canales (config) ni suscripciones push. */
return [
    'notify_rules' => [
        'plugin' => 'Aero.Notify', 'label' => 'reglas de notificación', 'model' => \Aero\Notify\Models\Rule::class,
        'fields' => 'id,event_id,audience,channel,template_id,delay_seconds,dedup_window_min,digest_window_min,max_per_hour,priority,is_enabled,sort_order,created_at',
        'filters' => 'event_id,audience,channel,is_enabled',
    ],
    'notify_templates' => [
        'plugin' => 'Aero.Notify', 'label' => 'plantillas de notificación', 'model' => \Aero\Notify\Models\Template::class,
        'fields' => 'id,event_id,code,channel,locale,subject,body,format,is_active', 'search' => 'code,subject,body', 'filters' => 'event_id,channel,locale,is_active',
    ],
    'notify_deliveries' => [
        'plugin' => 'Aero.Notify', 'label' => 'notificaciones enviadas', 'model' => \Aero\Notify\Models\Delivery::class,
        'fields' => 'id,event_id,rule_id,template_id,audience,channel,subject,body,status,attempts,error,sent_at,scheduled_at,created_at',
        'search' => 'subject', 'filters' => 'event_id,channel,status,audience',
    ],
];
