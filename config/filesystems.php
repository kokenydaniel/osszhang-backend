<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('SUPABASE_STORAGE_ACCESS_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('SUPABASE_STORAGE_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('SUPABASE_STORAGE_REGION', env('AWS_DEFAULT_REGION', 'eu-central-1')),
            'bucket' => env('SUPABASE_STORAGE_BUCKET', env('AWS_BUCKET')),
            'url' => env('SUPABASE_STORAGE_URL', env('AWS_URL')),
            'endpoint' => env('SUPABASE_STORAGE_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => filter_var(
                env('SUPABASE_STORAGE_USE_PATH_STYLE', env('AWS_USE_PATH_STYLE_ENDPOINT', true)),
                FILTER_VALIDATE_BOOL,
            ),
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
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
