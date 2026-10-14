<?php

/** Aero.Community — solo lectura; publicar y responder pasa por el panel (reglas de visibilidad y compromiso). */
return [
    'community_posts' => [
        'plugin' => 'Aero.Community', 'label' => 'publicaciones de la comunidad', 'model' => \Aero\Community\Models\Post::class,
        'fields' => 'id,type,visibility,status,title,body,area,official_response,responded_at,sla_due_at,votes_count,comments_count,created_at',
        'search' => 'title,body,area', 'filters' => 'type,status,visibility',
    ],
];
