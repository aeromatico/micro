<?php

/** Aero.Gym — socios, membresías, planes, clases, reservas, rutinas. Sin credenciales de acceso (qr_token, card). */
return [
    'gym_members' => [
        'plugin' => 'Aero.Gym', 'label' => 'socios del gimnasio', 'model' => \Aero\Gym\Models\Member::class,
        'fields' => 'id,name,phone,email,document,birthdate,card_number,status,notes,created_at',
        'search' => 'name,phone,email,document,card_number', 'filters' => 'status',
    ],
    'gym_memberships' => [
        'plugin' => 'Aero.Gym', 'label' => 'membresías', 'model' => \Aero\Gym\Models\Membership::class,
        'fields' => 'id,member_id,plan_id,starts_on,ends_on,status,price,currency,paid_at,payment_reference,renewed_from_id,cancelled_at,cancel_reason,created_at',
        'filters' => 'member_id,plan_id,status',
    ],
    'gym_plans' => [
        'plugin' => 'Aero.Gym', 'label' => 'planes del gimnasio', 'model' => \Aero\Gym\Models\Plan::class,
        'fields' => 'id,name,description,price,duration_days,classes_per_week,shop_product_id,is_active', 'filters' => 'is_active',
    ],
    'gym_class_types' => [
        'plugin' => 'Aero.Gym', 'label' => 'tipos de clase', 'model' => \Aero\Gym\Models\ClassType::class,
        'fields' => 'id,name,description,duration_minutes,default_capacity,color,is_active', 'filters' => 'is_active',
    ],
    'gym_sessions' => [
        'plugin' => 'Aero.Gym', 'label' => 'sesiones de clase', 'model' => \Aero\Gym\Models\ClassSession::class,
        'fields' => 'id,class_type_id,instructor_id,schedule_id,starts_at,ends_at,capacity,room,status',
        'filters' => 'class_type_id,instructor_id,status', 'order' => ['starts_at', 'desc'],
    ],
    'gym_schedules' => [
        'plugin' => 'Aero.Gym', 'label' => 'horarios de clase', 'model' => \Aero\Gym\Models\Schedule::class,
        'fields' => 'id,class_type_id,instructor_id,weekday,start_time,capacity,room,is_active', 'filters' => 'class_type_id,instructor_id,weekday,is_active',
    ],
    'gym_bookings' => [
        'plugin' => 'Aero.Gym', 'label' => 'reservas de clase', 'model' => \Aero\Gym\Models\Booking::class,
        'fields' => 'id,session_id,member_id,status,booked_at,cancelled_at,checked_in_at', 'filters' => 'session_id,member_id,status',
    ],
    'gym_instructors' => [
        'plugin' => 'Aero.Gym', 'label' => 'instructores', 'model' => \Aero\Gym\Models\Instructor::class,
        'fields' => 'id,name,phone,email,bio,is_active', 'search' => 'name,email', 'filters' => 'is_active',
    ],
    'gym_measurements' => [
        'plugin' => 'Aero.Gym', 'label' => 'mediciones corporales', 'model' => \Aero\Gym\Models\Measurement::class,
        'fields' => 'id,member_id,measured_on,weight_kg,height_cm,body_fat_pct,muscle_pct,waist_cm,chest_cm,hip_cm,arm_cm,thigh_cm,notes,source',
        'filters' => 'member_id', 'order' => ['measured_on', 'desc'],
    ],
    'gym_routines' => [
        'plugin' => 'Aero.Gym', 'label' => 'rutinas', 'model' => \Aero\Gym\Models\Routine::class,
        'fields' => 'id,member_id,instructor_id,name,goal,starts_on,ends_on,notes,is_active', 'search' => 'name,goal', 'filters' => 'member_id,instructor_id,is_active',
    ],
    'gym_routine_items' => [
        'plugin' => 'Aero.Gym', 'label' => 'ejercicios de rutina', 'model' => \Aero\Gym\Models\RoutineItem::class,
        'tenant_via' => ['routine_id', \Aero\Gym\Models\Routine::class],
        'fields' => 'id,routine_id,day_label,exercise,sets,reps,rest_seconds,notes,sort_order', 'filters' => 'routine_id',
        'order' => ['sort_order', 'asc'],
    ],
    'gym_groups' => [
        'plugin' => 'Aero.Gym', 'label' => 'grupos del gimnasio', 'model' => \Aero\Gym\Models\Group::class,
        'fields' => 'id,name,purpose,description,is_active', 'filters' => 'is_active',
    ],
    'gym_access_logs' => [
        'plugin' => 'Aero.Gym', 'label' => 'registros de acceso', 'model' => \Aero\Gym\Models\AccessLog::class,
        'fields' => 'id,member_id,method,granted,reason,session_id,created_at', 'filters' => 'member_id,granted,method',
    ],
];
