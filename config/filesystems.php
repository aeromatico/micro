<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. A "local" driver, as well as a variety of cloud
    | based drivers are available for your choosing. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', ''), '/').'/storage/app/public',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'uploads' => [
            'driver' => 'local',
            'root' => storage_path('app/uploads'),
            'url' => '/storage/app/uploads',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'media' => [
            'driver' => 'local',
            'root' => storage_path('app/media'),
            'url' => '/storage/app/media',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'resources' => [
            'driver' => 'local',
            'root' => storage_path('app/resources'),
            'url' => '/storage/app/resources',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],


        // Aero.Viral: muestras de video y documentos KYC. Bucket PRIVADO; acceso solo con URLs firmadas.
        // Sirve S3 o compatibles (Cloudflare R2, MinIO). Si VIRAL_S3_* está vacío usa AWS_*.
        'viral' => [
            'driver' => 's3',
            'key' => env('VIRAL_S3_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('VIRAL_S3_SECRET', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('VIRAL_S3_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'bucket' => env('VIRAL_S3_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('VIRAL_S3_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => (bool) env('VIRAL_S3_PATH_STYLE', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'root' => env('VIRAL_S3_PREFIX', 'viral'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    | For October CMS, we recommend using the `october:mirror` command instead
    |
    */

    'links' => [
        public_path('storage/app/public') => storage_path('app/public'),
    ],

];
