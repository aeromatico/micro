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
    'social_ad_accounts' => [
        'plugin' => 'Aero.Social', 'label' => 'cuentas publicitarias conectadas', 'model' => \Aero\Social\Models\AdAccount::class,
        'fields' => 'id,platform,external_id,name,currency,timezone,account_status,status,last_error,synced_at',
        'search' => 'name', 'filters' => 'platform,status',
    ],
    'social_campaigns' => [
        'plugin' => 'Aero.Social', 'label' => 'campañas de anuncios', 'model' => \Aero\Social\Models\Campaign::class,
        'fields' => 'id,ad_account_id,external_id,name,objective,status,effective_status,daily_budget,lifetime_budget,start_time,stop_time,post_target_id,synced_at',
        'search' => 'name', 'filters' => 'ad_account_id,status,effective_status',
    ],
    'social_adsets' => [
        'plugin' => 'Aero.Social', 'label' => 'conjuntos de anuncios', 'model' => \Aero\Social\Models\AdSet::class,
        'fields' => 'id,campaign_id,external_id,name,status,effective_status,daily_budget,lifetime_budget',
        'filters' => 'campaign_id,status',
    ],
    'social_insights' => [
        'plugin' => 'Aero.Social', 'label' => 'métricas diarias de campañas (gasto, impresiones, alcance, clics)', 'model' => \Aero\Social\Models\Insight::class,
        'fields' => 'id,campaign_id,date,spend,impressions,reach,clicks',
        'filters' => 'campaign_id,date',
    ],
];
