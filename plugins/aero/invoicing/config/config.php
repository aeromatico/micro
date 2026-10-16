<?php

/**
 * Los secretos viven en .env, nunca en SettingsModel (sin cifrado).
 * FLOOGOS_CA_BUNDLE: ruta a un PEM con las CA del sistema + el intermedio de
 * Floogos (su servidor no lo envía y cURL falla al verificar).
 */
return [
    'floogos' => [
        'url'       => env('FLOOGOS_WEBHOOK_URL'),
        'secret'    => env('FLOOGOS_WEBHOOK_SECRET'),
        'ca_bundle' => env('FLOOGOS_CA_BUNDLE'),
        'timeout'   => (int) env('FLOOGOS_TIMEOUT', 30),
    ],
];
