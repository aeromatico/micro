<?php

/** Aero.Livechat — conversaciones del chat en vivo. Sin tokens de visitante ni de adjuntos. */
return [
    'livechat_conversations' => [
        'plugin' => 'Aero.Livechat', 'label' => 'conversaciones del chat en vivo', 'model' => \Aero\Livechat\Models\Conversation::class,
        'fields' => 'id,inbox_id,contact_id,status,assigned_to,agent_unread_count,visitor_unread_count,page_url,last_message_at,session_started_at,created_at',
        'filters' => 'inbox_id,contact_id,status,assigned_to', 'order' => ['last_message_at', 'desc'],
    ],
    'livechat_messages' => [
        'plugin' => 'Aero.Livechat', 'label' => 'mensajes del chat en vivo', 'model' => \Aero\Livechat\Models\Message::class,
        'tenant_via' => ['conversation_id', \Aero\Livechat\Models\Conversation::class],
        'fields' => 'id,conversation_id,sender_type,sender_id,body,attachment_name,attachment_mime,attachment_size,created_at',
        'search' => 'body', 'filters' => 'conversation_id,sender_type',
    ],
    'livechat_contacts' => [
        'plugin' => 'Aero.Livechat', 'label' => 'visitantes del chat en vivo', 'model' => \Aero\Livechat\Models\Contact::class,
        'fields' => 'id,name,email,phone,last_seen_at,is_banned,banned_until,created_at', 'search' => 'name,email,phone', 'filters' => 'is_banned',
    ],
    'livechat_inboxes' => [
        'plugin' => 'Aero.Livechat', 'label' => 'bandejas del chat en vivo', 'model' => \Aero\Livechat\Models\Inbox::class,
        'fields' => 'id,name,welcome_message,color,is_active,created_at', 'filters' => 'is_active',
    ],
];
