<?php

/** Aero.Tickets — eventos del tenant. Solo lectura. El enlace privado (access_token) nunca se expone. */
return [
    'tickets_events' => [
        'plugin' => 'Aero.Tickets', 'label' => 'eventos (públicos, privados y mixtos)', 'model' => \Aero\Tickets\Models\Event::class,
        'fields' => 'id,category_id,name,slug,description,visibility,status,starts_at,ends_at,department,city,zone,venue_name,address,address_reference,lat,lng,capacity,moderation,buyer_extra_label,buyer_extra_required,min_age,requirements,organizer_name,contact_name,contact_phone,contact_whatsapp,contact_email,published_at,created_at',
        'search' => 'name,city,zone,venue_name', 'filters' => 'category_id,visibility,status,city,department',
        'order' => ['starts_at', 'desc'],
    ],
    'tickets_ticket_types' => [
        'plugin' => 'Aero.Tickets', 'label' => 'tipos de entrada de los eventos', 'model' => \Aero\Tickets\Models\TicketType::class,
        'fields' => 'id,event_id,session_id,name,description,kind,price,currency,fee_percent,fee_fixed,fee_payer,quantity,sales_start,sales_end,max_per_order,is_active,created_at',
        'filters' => 'event_id,kind,is_active', 'search' => 'name',
    ],
    'tickets_attendees' => [
        'plugin' => 'Aero.Tickets', 'label' => 'entradas emitidas y asistentes', 'model' => \Aero\Tickets\Models\Ticket::class,
        'fields' => 'id,event_id,ticket_type_id,seq,code,holder_name,holder_email,holder_phone,status,source,base_amount,fee_amount,amount,currency,order_code,order_id,expires_at,refund_pending,paid_at,checked_in_at,created_at',
        'filters' => 'event_id,ticket_type_id,status,source', 'search' => 'code,holder_name,holder_email,holder_phone',
        'order' => ['id', 'desc'],
    ],
    'tickets_orders' => [
        'plugin' => 'Aero.Tickets', 'label' => 'órdenes de compra de entradas', 'model' => \Aero\Tickets\Models\Order::class,
        'fields' => 'id,event_id,code,status,source,buyer_name,buyer_email,buyer_phone,buyer_document,buyer_extra,currency,subtotal,fees,total,quantity,expires_at,commission_total,payment_reference,verifying_at,confirmed_at,cancelled_at,cancel_reason,incident_note,created_at',
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
    'tickets_scans' => [
        'plugin' => 'Aero.Tickets', 'label' => 'registro de accesos (lecturas en puerta, resultado y operador)', 'model' => \Aero\Tickets\Models\Scan::class,
        'fields' => 'id,event_id,ticket_id,code,holder_name,result,method,operator_type,operator_name,message,scanned_at',
        'search' => 'holder_name,code,operator_name', 'filters' => 'event_id,result,method,operator_type', 'order' => ['id', 'desc'],
    ],
    'tickets_scanners' => [
        'plugin' => 'Aero.Tickets', 'label' => 'escáneres (personal y dispositivos de puerta; sin el enlace secreto)', 'model' => \Aero\Tickets\Models\Scanner::class,
        'fields' => 'id,name,is_active,last_seen_at,created_by,created_at', 'filters' => 'is_active', 'search' => 'name',
    ],
    'tickets_organizer_profile' => [
        'plugin' => 'Aero.Tickets', 'label' => 'estado del organizador (aprobación) de entradas', 'model' => \Aero\Tickets\Models\Organizer::class,
        'fields' => 'id,status,legal_name,contact_name,contact_email,contact_phone,city,requested_at,decided_at,decision_note',
        'filters' => 'status',
    ],
    'tickets_commissions' => [
        'plugin' => 'Aero.Tickets', 'label' => 'comisiones de la plataforma por orden cobrada', 'model' => \Aero\Tickets\Models\CommissionEntry::class,
        'fields' => 'id,event_id,order_id,currency,base_amount,commission_amount,payer,status,accrued_at,voided_at',
        'filters' => 'event_id,status,payer', 'order' => ['id', 'desc'],
    ],
];
