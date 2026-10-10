<?php

/** Aero.Chat (PWA multiagente) — cobros, pedidos y eventos de conversaciones. Sin tokens de dispositivo. */
return [
    'chat_charges' => [
        'plugin' => 'Aero.Chat', 'label' => 'cobros desde el chat', 'model' => \Aero\Chat\Models\ChatCharge::class,
        'fields' => 'id,conversation_id,user_id,qr_code_id,amount,currency,description,status,due_at,paid_at,created_at', 'filters' => 'conversation_id,status',
    ],
    'chat_orders' => [
        'plugin' => 'Aero.Chat', 'label' => 'pedidos desde el chat', 'model' => \Aero\Chat\Models\ChatOrder::class,
        'fields' => 'id,conversation_id,user_id,order_id,created_at', 'filters' => 'conversation_id,order_id',
    ],
    'chat_events' => [
        'plugin' => 'Aero.Chat', 'label' => 'eventos del chat', 'model' => \Aero\Chat\Models\ChatEvent::class,
        'fields' => 'id,conversation_id,user_id,type,body,created_at', 'filters' => 'conversation_id,type',
    ],
];
