<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Flagsmith Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Flagsmith feature flag service integration.
    |
    */

    'enabled' => env('FLAGSMITH_ENABLED', true),

    'environment_key' => env('FLAGSMITH_ENVIRONMENT_KEY', 'ser.XtgjjdGYNq9EdMRXP6gSrX'),

    'api_url' => env('FLAGSMITH_API_URL', 'https://edge.api.flagsmith.com/api/v1/'),

    'cache' => [
        'ttl' => env('FLAGSMITH_CACHE_TTL', 300), // 5 minutes
        'prefix' => env('FLAGSMITH_CACHE_PREFIX', 'flagsmith_'),
    ],

    'fallback' => [
        'enabled' => env('FLAGSMITH_FALLBACK_ENABLED', true),
        'log_failures' => env('FLAGSMITH_LOG_FAILURES', true),
    ],

    'default_flags' => [
        // Define default flag values here as fallbacks
        // 'feature_name' => false,
        // 'maintenance_mode' => false,
        // 'new_ui_enabled' => false,
    ],
];