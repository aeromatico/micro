<?php

/** Aero.Hello — mensajería. Sin API keys, perfiles con credenciales, ajustes de webhook ni entregas de webhook. */
return [
    'hello_conversations' => [
        'plugin' => 'Aero.Hello', 'label' => 'conversaciones de mensajería', 'model' => \Aero\Hello\Models\Conversation::class,
        'fields' => 'id,account_id,contact_id,code,status,assigned_to,last_message_at,unread_count,is_archived,is_muted,created_at',
        'filters' => 'account_id,contact_id,status,assigned_to,is_archived', 'order' => ['last_message_at', 'desc'],
    ],
    'hello_messages' => [
        'plugin' => 'Aero.Hello', 'label' => 'mensajes', 'model' => \Aero\Hello\Models\Message::class,
        'tenant_via' => ['conversation_id', \Aero\Hello\Models\Conversation::class],
        'fields' => 'id,conversation_id,account_id,contact_id,direction,type,body,media_url,media_type,status,failed_reason,sent_at,delivered_at,read_at,created_at',
        'search' => 'body', 'filters' => 'conversation_id,contact_id,direction,type,status',
    ],
    'hello_contacts' => [
        'plugin' => 'Aero.Hello', 'label' => 'contactos de mensajería', 'model' => \Aero\Hello\Models\Contact::class,
        'fields' => 'id,name,notes,is_blocked,last_contacted_at,created_at', 'search' => 'name,notes', 'filters' => 'is_blocked',
    ],
    'hello_accounts' => [
        'plugin' => 'Aero.Hello', 'label' => 'cuentas de mensajería conectadas', 'model' => \Aero\Hello\Models\Account::class,
        'fields' => 'id,driver,platform,label,external_username,phone_number,status,is_enabled,connected_at,abbreviation',
        'filters' => 'driver,platform,status,is_enabled',
    ],
    'hello_templates' => [
        'plugin' => 'Aero.Hello', 'label' => 'plantillas de mensajes', 'model' => \Aero\Hello\Models\Template::class,
        'fields' => 'id,name,channel_type,body,variables,provider_template_name,provider_language', 'search' => 'name,body', 'filters' => 'channel_type',
    ],
    'hello_campaigns' => [
        'plugin' => 'Aero.Hello', 'label' => 'campañas', 'model' => \Aero\Hello\Models\Campaign::class,
        'fields' => 'id,name,account_id,template_id,status,scheduled_at,started_at,completed_at,created_at', 'search' => 'name', 'filters' => 'status,account_id,template_id',
    ],
    'hello_calls' => [
        'plugin' => 'Aero.Hello', 'label' => 'llamadas', 'model' => \Aero\Hello\Models\Call::class,
        'fields' => 'id,account_id,contact_id,conversation_id,direction,status,from_number,to_number,started_at,ended_at,duration_seconds,end_reason,billable_cost_usd,created_at',
        'filters' => 'account_id,contact_id,direction,status',
    ],
    'hello_posts' => [
        'plugin' => 'Aero.Hello', 'label' => 'publicaciones sociales', 'model' => \Aero\Hello\Models\Post::class,
        'fields' => 'id,content,status,scheduled_at,published_at,error_message,created_at', 'search' => 'content', 'filters' => 'status',
    ],
];
