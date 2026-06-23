<?php

return [

    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),

    'project' => env('OPENAI_PROJECT'),

    'base_uri' => env('OPENAI_BASE_URL'),

    'request_timeout' => env('OPENAI_REQUEST_TIMEOUT', 30),

    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),

    'temperature' => (float) env('OPENAI_TEMPERATURE', 0.7),

    'pricing' => [
        'overrides' => is_array($openAiPricingOverrides = json_decode((string) env('OPENAI_MODEL_PRICING', '{}'), true))
            ? $openAiPricingOverrides
            : [],
    ],
];
