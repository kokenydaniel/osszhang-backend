<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

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
            'region' => env('SUPABASE_STORAGE_REGION', env('AWS_DEFAULT_REGION', 'eu-west-1')),
            'bucket' => env('SUPABASE_STORAGE_BUCKET', env('AWS_BUCKET')),
            'url' => env('SUPABASE_STORAGE_URL', env('AWS_URL')),
            'endpoint' => env('SUPABASE_STORAGE_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => filter_var(
                env('SUPABASE_STORAGE_USE_PATH_STYLE', env('AWS_USE_PATH_STYLE_ENDPOINT', true)),
                FILTER_VALIDATE_BOOL,
            ),
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            'throw' => true,
            'report' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
