<?php

$frontendOrigins = array_values(array_unique(array_filter([
    env('FRONTEND_URL'),
    'https://osszhang.vercel.app',
    'http://localhost:3000',
    'http://127.0.0.1:3000',
])));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $frontendOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition', 'Content-Length', 'Content-Type'],

    'max_age' => 0,

    'supports_credentials' => false,

];
