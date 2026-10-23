<?php

/**
 * Aero.Church — SOLO lectura y SOLO datos de gestión. Quedan fuera por diseño: contacto y documento de las
 * personas, peticiones de oración, aportes con aportante, comunicados y destinatarios, y la asistencia
 * individual (ver `_excluded.php`). Las listas ocultan lo que no está en `fields`.
 */
return [
    'church_members' => [
        'plugin' => 'Aero.Church', 'label' => 'miembros (nombre, estado y sede; sin contacto, documento ni notas)', 'model' => \Aero\Church\Models\Member::class,
        'fields' => 'id,first_name,last_name,status,branch_id,family_id,baptized,joined_at,created_at',
        'search' => 'first_name,last_name', 'filters' => 'status,branch_id,family_id',
    ],
    'church_branches' => [
        'plugin' => 'Aero.Church', 'label' => 'sedes de la iglesia', 'model' => \Aero\Church\Models\Branch::class,
        'fields' => 'id,name,city,address,phone,is_main,is_active', 'search' => 'name,city', 'filters' => 'is_active',
    ],
    'church_families' => [
        'plugin' => 'Aero.Church', 'label' => 'familias (solo el nombre)', 'model' => \Aero\Church\Models\Family::class,
        'fields' => 'id,name', 'search' => 'name',
    ],
    'church_groups' => [
        'plugin' => 'Aero.Church', 'label' => 'ministerios, células y grupos', 'model' => \Aero\Church\Models\Group::class,
        'fields' => 'id,name,type,branch_id,meeting_day,meeting_time,location,is_active', 'search' => 'name', 'filters' => 'type,branch_id,is_active',
    ],
    'church_meetings' => [
        'plugin' => 'Aero.Church', 'label' => 'reuniones de grupos (sin asistencia individual)', 'model' => \Aero\Church\Models\Meeting::class,
        'fields' => 'id,group_id,title,meeting_date,start_time,location', 'filters' => 'group_id', 'order' => ['meeting_date', 'desc'],
    ],
    'church_events' => [
        'plugin' => 'Aero.Church', 'label' => 'eventos de la iglesia', 'model' => \Aero\Church\Models\ChurchEvent::class,
        'fields' => 'id,title,type,starts_at,ends_at,location,capacity,status,is_public,branch_id,group_id', 'search' => 'title',
        'filters' => 'type,status,is_public,branch_id', 'order' => ['starts_at', 'desc'],
    ],
    'church_funds' => [
        'plugin' => 'Aero.Church', 'label' => 'conceptos de aporte y campañas (sin aportes ni aportantes)', 'model' => \Aero\Church\Models\Fund::class,
        'fields' => 'id,name,kind,goal_amount,starts_on,ends_on,is_active', 'search' => 'name', 'filters' => 'kind,is_active',
    ],
    'church_visits' => [
        'plugin' => 'Aero.Church', 'label' => 'visitantes y su seguimiento (sin contacto ni interés)', 'model' => \Aero\Church\Models\Visit::class,
        'fields' => 'id,name,status,visit_date,source,branch_id,responsible_id,next_action_date', 'search' => 'name',
        'filters' => 'status,source,branch_id', 'order' => ['id', 'desc'],
    ],
    'church_sermons' => [
        'plugin' => 'Aero.Church', 'label' => 'predicaciones', 'model' => \Aero\Church\Models\Sermon::class,
        'fields' => 'id,title,preacher,topic,preached_on,series_id,visibility,is_published', 'search' => 'title,preacher,topic',
        'filters' => 'series_id,visibility,is_published', 'order' => ['preached_on', 'desc'],
    ],
    'church_series' => [
        'plugin' => 'Aero.Church', 'label' => 'series de enseñanzas', 'model' => \Aero\Church\Models\Series::class,
        'fields' => 'id,name,description', 'search' => 'name',
    ],
    'church_resources' => [
        'plugin' => 'Aero.Church', 'label' => 'recursos descargables (sin el archivo)', 'model' => \Aero\Church\Models\Resource::class,
        'fields' => 'id,title,kind,visibility,is_published,sermon_id', 'search' => 'title', 'filters' => 'kind,visibility,is_published',
    ],
    'church_posts' => [
        'plugin' => 'Aero.Church', 'label' => 'noticias y comunicados publicados en el sitio', 'model' => \Aero\Church\Models\Post::class,
        'fields' => 'id,title,kind,visibility,is_published,is_pinned,published_at', 'search' => 'title',
        'filters' => 'kind,visibility,is_published', 'order' => ['published_at', 'desc'],
    ],
];
