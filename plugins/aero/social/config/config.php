<?php

/**
 * Llaves de las apps de la plataforma: SOLO en .env, nunca en Settings ni en
 * el repo. Un tenant puede usar las suyas (Redes sociales → Llaves propias).
 */
return [
    'meta' => [
        'app_id'        => env('META_APP_ID'),
        'app_secret'    => env('META_APP_SECRET'),
        'graph_version' => env('META_GRAPH_API_VERSION', 'v21.0'),
        // Opcional: fuerza la URI de retorno (debe coincidir con la registrada en cada red).
        'redirect_uri'  => env('META_SOCIAL_REDIRECT_URI'),
    ],
    'youtube' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        // Proyectos de Google sin auditar: YouTube deja los videos en privado igual.
        'privacy'       => env('SOCIAL_YOUTUBE_PRIVACY', 'public'),
    ],
    'x' => [
        'client_id'     => env('X_CLIENT_ID'),
        'client_secret' => env('X_CLIENT_SECRET'),
    ],
    'tiktok' => [
        'client_key'    => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        // Hasta que TikTok audite la app, solo se puede publicar en privado (SELF_ONLY).
        'audited'       => (bool) env('TIKTOK_AUDITED', false),
    ],
];
