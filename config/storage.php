<?php

return [
    'disk' => env('FILESYSTEM_DISK'),
    'bucket' => env('SUPABASE_STORAGE_BUCKET', env('AWS_BUCKET')),
    'endpoint' => env('SUPABASE_STORAGE_ENDPOINT', env('AWS_ENDPOINT')),
    'access_key' => env('SUPABASE_STORAGE_ACCESS_KEY', env('AWS_ACCESS_KEY_ID')),
    'secret_key' => env('SUPABASE_STORAGE_SECRET_KEY', env('AWS_SECRET_ACCESS_KEY')),
    'region' => env('SUPABASE_STORAGE_REGION', env('AWS_DEFAULT_REGION', 'eu-west-1')),
    'use_path_style' => env('SUPABASE_STORAGE_USE_PATH_STYLE', env('AWS_USE_PATH_STYLE_ENDPOINT', true)),
];
