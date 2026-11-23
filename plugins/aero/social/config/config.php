<?php

/** Llaves de la app de Meta de la plataforma: SOLO en .env, nunca en Settings ni en el repo. */
return [
    'meta' => [
        'app_id'        => env('META_APP_ID'),
        'app_secret'    => env('META_APP_SECRET'),
        'graph_version' => env('META_GRAPH_API_VERSION', 'v21.0'),
        // Opcional: fuerza la URI de retorno (debe coincidir con la registrada en Meta).
        'redirect_uri'  => env('META_SOCIAL_REDIRECT_URI'),
    ],
];
