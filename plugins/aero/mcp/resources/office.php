<?php

/** Aero.Office — reservas: negocio, sucursales, servicios, profesionales, clientes y citas. Sin tokens de gestión. */
return [
    'office_branches' => [
        'plugin' => 'Aero.Office', 'label' => 'sucursales', 'model' => \Aero\Office\Models\Branch::class,
        'fields' => 'id,name,address,phone,lat,lng,hours,is_active', 'search' => 'name,address', 'filters' => 'is_active',
    ],
    'office_services' => [
        'plugin' => 'Aero.Office', 'label' => 'servicios reservables', 'model' => \Aero\Office\Models\Service::class,
        'fields' => 'id,name,category,description,duration_minutes,buffer_minutes,price,requires_approval,is_active,is_public',
        'search' => 'name,category', 'filters' => 'category,is_active,is_public',
    ],
    'office_workers' => [
        'plugin' => 'Aero.Office', 'label' => 'profesionales', 'model' => \Aero\Office\Models\Worker::class,
        'fields' => 'id,name,title,phone,email,bio,hours,is_active,is_public', 'search' => 'name,title,email', 'filters' => 'is_active,is_public',
    ],
    'office_time_offs' => [
        'plugin' => 'Aero.Office', 'label' => 'ausencias y feriados', 'model' => \Aero\Office\Models\TimeOff::class,
        'fields' => 'id,worker_id,branch_id,type,starts_at,ends_at,reason', 'filters' => 'worker_id,branch_id,type',
        'order' => ['starts_at', 'desc'],
    ],
    'office_customers' => [
        'plugin' => 'Aero.Office', 'label' => 'clientes', 'model' => \Aero\Office\Models\Customer::class,
        'fields' => 'id,name,phone,email,document,notes,created_at', 'search' => 'name,phone,email,document',
    ],
    'office_bookings' => [
        'plugin' => 'Aero.Office', 'label' => 'reservas', 'model' => \Aero\Office\Models\Booking::class,
        'fields' => 'id,code,branch_id,service_id,worker_id,customer_id,starts_at,ends_at,status,source,service_name,duration_minutes,price,currency,worker_name,customer_notes,cancel_reason,created_at',
        'search' => 'code,service_name,worker_name', 'filters' => 'branch_id,service_id,worker_id,customer_id,status,source',
        'order' => ['starts_at', 'desc'],
    ],
    'office_booking_logs' => [
        'plugin' => 'Aero.Office', 'label' => 'historial de reservas', 'model' => \Aero\Office\Models\BookingLog::class,
        'fields' => 'id,booking_id,actor,action,changes,note,created_at', 'filters' => 'booking_id,action',
        'order' => ['id', 'desc'],
    ],
];
