<?php

/** Aero.Hub — trabajos de media. */
return [
    'hub_media_jobs' => [
        'plugin' => 'Aero.Hub', 'label' => 'trabajos de media', 'model' => \Aero\Hub\Models\HubMediaJob::class,
        'fields' => 'id,job_id,endpoint_code,status,created_at', 'filters' => 'endpoint_code,status',
    ],
];
