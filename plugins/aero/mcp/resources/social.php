<?php

/** Aero.Social — solo lectura (publicar cobra créditos y pasa por el Publisher). Nunca exponer access_token. */
return [
    'social_accounts' => [
        'plugin' => 'Aero.Social', 'label' => 'cuentas de redes sociales conectadas', 'model' => \Aero\Social\Models\Account::class,
        'fields' => 'id,network,external_id,name,username,status,last_error,checked_at,created_at',
        'search' => 'name,username', 'filters' => 'network,status',
    ],
    'social_posts' => [
        'plugin' => 'Aero.Social', 'label' => 'publicaciones en redes sociales', 'model' => \Aero\Social\Models\Post::class,
        'fields' => 'id,content,link,status,scheduled_at,published_at,created_at',
        'search' => 'content', 'filters' => 'status',
    ],
    'social_post_targets' => [
        'plugin' => 'Aero.Social', 'label' => 'publicación por red (estado, enlace, error)', 'model' => \Aero\Social\Models\PostTarget::class,
        'fields' => 'id,post_id,account_id,network,status,permalink,error,credits_charged,published_at,created_at',
        'filters' => 'post_id,account_id,network,status',
    ],
];
