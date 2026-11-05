<?php

/** Aero.Tickets — eventos del tenant. Solo lectura. El enlace privado (access_token) nunca se expone. */
return [
    'tickets_events' => [
        'plugin' => 'Aero.Tickets', 'label' => 'eventos (públicos, privados y mixtos)', 'model' => \Aero\Tickets\Models\Event::class,
        'fields' => 'id,category_id,name,slug,description,visibility,status,starts_at,ends_at,department,city,zone,venue_name,address,address_reference,lat,lng,capacity,reservation_minutes,buyer_extra_label,buyer_extra_required,min_age,requirements,organizer_name,contact_name,contact_phone,contact_whatsapp,contact_email,published_at,created_at',
        'search' => 'name,city,zone,venue_name', 'filters' => 'category_id,visibility,status,city,department',
        'order' => ['starts_at', 'desc'],
    ],
    'tickets_ticket_types' => [
        'plugin' => 'Aero.Tickets', 'label' => 'tipos de entrada de los eventos', 'model' => \Aero\Tickets\Models\TicketType::class,
        'fields' => 'id,event_id,session_id,name,description,kind,price,currency,fee_percent,fee_fixed,fee_payer,quantity,sales_start,sales_end,max_per_order,is_active,created_at',
        'filters' => 'event_id,kind,is_active', 'search' => 'name',
    ],
    'tickets_guests' => [
        'plugin' => 'Aero.Tickets', 'label' => 'invitados de eventos (sin enlace de invitación)', 'model' => \Aero\Tickets\Models\Guest::class,
        'fields' => 'id,event_id,ticket_type_id,name,email,phone,party_size,companions_allowed,invitation_kind,status,rsvp,confirmed_count,invited_at,responded_at,revoked_at,revoke_reason,created_at',
        'filters' => 'event_id,rsvp,status,invitation_kind', 'search' => 'name,email,phone',
    ],
    'tickets_attendees' => [
        'plugin' => 'Aero.Tickets', 'label' => 'entradas emitidas y asistentes', 'model' => \Aero\Tickets\Models\Ticket::class,
        'fields' => 'id,event_id,ticket_type_id,guest_id,code,holder_name,holder_email,holder_phone,status,source,base_amount,fee_amount,amount,currency,order_code,order_id,expires_at,refund_pending,paid_at,checked_in_at,created_at',
        'filters' => 'event_id,ticket_type_id,status,source', 'search' => 'code,holder_name,holder_email,holder_phone',
        'order' => ['id', 'desc'],
    ],
    'tickets_orders' => [
        'plugin' => 'Aero.Tickets', 'label' => 'órdenes de compra de entradas', 'model' => \Aero\Tickets\Models\Order::class,
        'fields' => 'id,event_id,guest_id,code,status,source,buyer_name,buyer_email,buyer_phone,buyer_document,buyer_extra,currency,subtotal,fees,total,quantity,expires_at,payment_reference,verifying_at,confirmed_at,cancelled_at,cancel_reason,incident_note,created_at',
        'search' => 'code,buyer_name,buyer_email,buyer_phone', 'filters' => 'event_id,status,source', 'order' => ['id', 'desc'],
    ],
    'tickets_order_items' => [
        'plugin' => 'Aero.Tickets', 'label' => 'líneas de las órdenes (tipo, cantidad, precios)', 'model' => \Aero\Tickets\Models\OrderItem::class,
        'fields' => 'id,order_id,ticket_type_id,name,kind,quantity,unit_price,unit_fee,subtotal,fees,total,created_at',
        'filters' => 'order_id,ticket_type_id,kind',
    ],
    'tickets_payments' => [
        'plugin' => 'Aero.Tickets', 'label' => 'pagos de las órdenes de entradas (QR, transferencia, manual)', 'model' => \Aero\Tickets\Models\Payment::class,
        'fields' => 'id,event_id,order_id,method,provider,status,currency,amount,amount_received,reference,sender_name,is_late,is_suspicious,flag_reason,reject_reason,verified_by_name,verified_by_actor,refund_reference,submitted_at,verified_at,refunded_at,created_at',
        'search' => 'reference,sender_name', 'filters' => 'event_id,order_id,method,status,is_late,is_suspicious', 'order' => ['id', 'desc'],
    ],
    'tickets_payment_history' => [
        'plugin' => 'Aero.Tickets', 'label' => 'historial financiero de pagos (quién hizo qué)', 'model' => \Aero\Tickets\Models\PaymentEvent::class,
        'fields' => 'id,payment_id,order_id,action,actor_type,actor_name,from_status,to_status,amount,note,created_at',
        'filters' => 'payment_id,order_id,action,actor_type', 'order' => ['id', 'desc'],
    ],
];
