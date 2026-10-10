<?php

/** Aero.Tracking — flotas, trabajos, paradas, posiciones y feeds. Sin tokens de ingesta ni URLs de feeds de terceros. */
return [
    'tracking_assets' => [
        'plugin' => 'Aero.Tracking', 'label' => 'activos rastreados', 'model' => \Aero\Tracking\Models\Asset::class,
        'fields' => 'id,name,type,code,is_active,last_lat,last_lng,last_speed,last_heading,last_battery,last_seen_at,created_at',
        'search' => 'name,code', 'filters' => 'type,is_active',
    ],
    'tracking_jobs' => [
        'plugin' => 'Aero.Tracking', 'label' => 'trabajos de rastreo', 'model' => \Aero\Tracking\Models\Job::class,
        'fields' => 'id,uuid,asset_id,reference,external_type,external_id,title,notes,status,scheduled_at,started_at,completed_at,created_at',
        'search' => 'reference,title,external_id', 'filters' => 'asset_id,status,external_type',
    ],
    'tracking_stops' => [
        'plugin' => 'Aero.Tracking', 'label' => 'paradas de trabajos', 'model' => \Aero\Tracking\Models\Stop::class,
        'fields' => 'id,job_id,route_id,sequence,type,name,address,lat,lng,contact_name,contact_phone,window_start,window_end,service_minutes,status,arrived_at,completed_at,notes',
        'filters' => 'job_id,status,type', 'order' => ['sequence', 'asc'],
    ],
    'tracking_positions' => [
        'plugin' => 'Aero.Tracking', 'label' => 'posiciones', 'model' => \Aero\Tracking\Models\Position::class,
        'fields' => 'id,asset_id,lat,lng,speed,heading,accuracy,altitude,battery,recorded_at', 'filters' => 'asset_id', 'order' => ['recorded_at', 'desc'],
    ],
    'tracking_feeds' => [
        'plugin' => 'Aero.Tracking', 'label' => 'feeds de seguimiento', 'model' => \Aero\Tracking\Models\Feed::class,
        'fields' => 'id,uuid,job_id,asset_id,provider,label,status,phase,eta_text,delay_label,message,last_polled_at,finished_at,created_at',
        'search' => 'label', 'filters' => 'provider,status,asset_id,job_id',
    ],
    'tracking_feed_events' => [
        'plugin' => 'Aero.Tracking', 'label' => 'eventos de feeds', 'model' => \Aero\Tracking\Models\FeedEvent::class,
        'fields' => 'id,feed_id,type,data,occurred_at', 'filters' => 'feed_id,type', 'order' => ['occurred_at', 'desc'],
    ],
];
