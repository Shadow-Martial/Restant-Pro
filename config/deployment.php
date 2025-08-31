<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deployment Environments
    |--------------------------------------------------------------------------
    |
    | Define the deployment environments and their configurations
    |
    */
    'environments' => [
        'production' => [
            'subdomain' => 'main',
            'branch' => 'main',
            'dokku_app' => 'restant-main'
        ],
        'staging' => [
            'subdomain' => 'staging',
            'branch' => 'staging',
            'dokku_app' => 'restant-staging'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring services integration
    |
    */
    'monitoring' => [
        'sentry' => [
            'enabled' => env('SENTRY_ENABLED', true),
            'dsn' => env('SENTRY_LARAVEL_DSN'),
            'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
            'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production'))
        ],
        'flagsmith' => [
            'enabled' => env('FLAGSMITH_ENABLED', true),
            'environment_key' => env('FLAGSMITH_ENVIRONMENT_KEY'),
            'api_url' => env('FLAGSMITH_API_URL', 'https://edge.api.flagsmith.com/api/v1/')
        ],
        'grafana' => [
            'enabled' => env('GRAFANA_ENABLED', true),
            'api_key' => env('GRAFANA_CLOUD_API_KEY'),
            'instance_id' => env('GRAFANA_CLOUD_INSTANCE_ID'),
            'logs_url' => env('GRAFANA_LOGS_URL', 'https://logs-prod-028.grafana.net'),
            'logs_user' => env('GRAFANA_LOGS_USER'),
            'logs_password' => env('GRAFANA_LOGS_PASSWORD')
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Notifications
    |--------------------------------------------------------------------------
    |
    | Configure notification channels for deployment events
    |
    */
    'notifications' => [
        'channels' => [
            'slack' => [
                'enabled' => env('DEPLOYMENT_SLACK_ENABLED', false),
                'webhook_url' => env('DEPLOYMENT_SLACK_WEBHOOK_URL'),
            ],
            'email' => [
                'enabled' => env('DEPLOYMENT_EMAIL_ENABLED', false),
                'recipients' => array_filter(explode(',', env('DEPLOYMENT_EMAIL_RECIPIENTS', ''))),
            ],
            'webhook' => [
                'enabled' => env('DEPLOYMENT_WEBHOOK_ENABLED', false),
                'url' => env('DEPLOYMENT_WEBHOOK_URL'),
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => env('DEPLOYMENT_WEBHOOK_AUTH_HEADER'),
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for deployment health checks
    |
    */
    'health_checks' => [
        'enabled' => env('DEPLOYMENT_HEALTH_CHECKS_ENABLED', true),
        'timeout' => env('DEPLOYMENT_HEALTH_CHECK_TIMEOUT', 30),
        'retries' => env('DEPLOYMENT_HEALTH_CHECK_RETRIES', 3),
        'endpoints' => [
            'app' => '/health',
            'database' => '/health/database',
            'cache' => '/health/cache',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rollback Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic rollback functionality
    |
    */
    'rollback' => [
        'enabled' => env('DEPLOYMENT_ROLLBACK_ENABLED', true),
        'auto_rollback_on_failure' => env('DEPLOYMENT_AUTO_ROLLBACK', true),
        'max_rollback_attempts' => env('DEPLOYMENT_MAX_ROLLBACK_ATTEMPTS', 3),
    ],
];