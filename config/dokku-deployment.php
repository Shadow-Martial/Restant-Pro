<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dokku Deployment Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file defines the settings for Dokku-based deployment
    | of the Laravel multi-tenant SaaS platform.
    |
    */

    'server' => [
        'host' => env('DOKKU_HOST', '209.50.227.94'),
        'user' => env('DOKKU_USER', 'dokku'),
        'base_domain' => env('DOKKU_BASE_DOMAIN', 'susankshakya.com.np'),
    ],

    'environments' => [
        'production' => [
            'app_name' => 'restant-main',
            'subdomain' => 'main',
            'branch' => 'main',
            'domain' => 'restant.main.susankshakya.com.np',
            'ssl_enabled' => true,
            'debug' => false,
            'log_level' => 'error',
        ],
        'staging' => [
            'app_name' => 'restant-staging',
            'subdomain' => 'staging',
            'branch' => 'staging',
            'domain' => 'restant.staging.susankshakya.com.np',
            'ssl_enabled' => true,
            'debug' => true,
            'log_level' => 'debug',
        ],
    ],

    'services' => [
        'mysql' => [
            'service_name' => 'mysql-restant',
            'version' => '8.0',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
        'redis' => [
            'service_name' => 'redis-restant',
            'version' => '7.0',
            'maxmemory' => '256mb',
            'maxmemory_policy' => 'allkeys-lru',
        ],
    ],

    'monitoring' => [
        'sentry' => [
            'enabled' => env('SENTRY_ENABLED', true),
            'dsn' => env('SENTRY_LARAVEL_DSN'),
            'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
            'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),
            'environment' => env('SENTRY_ENVIRONMENT'),
        ],
        'flagsmith' => [
            'enabled' => env('FLAGSMITH_ENABLED', true),
            'environment_key' => env('FLAGSMITH_ENVIRONMENT_KEY'),
            'api_url' => env('FLAGSMITH_API_URL', 'https://flagsmith.susankshakya.com.np/api/v1/'),
            'default_flag_handler' => env('FLAGSMITH_DEFAULT_FLAG_HANDLER', 'default_value'),
        ],
        'grafana' => [
            'enabled' => env('GRAFANA_ENABLED', true),
            'api_key' => env('GRAFANA_CLOUD_API_KEY'),
            'instance_id' => env('GRAFANA_CLOUD_INSTANCE_ID'),
            'metrics_endpoint' => env('GRAFANA_METRICS_ENDPOINT'),
        ],
    ],

    'deployment' => [
        'php_version' => '8.1',
        'node_version' => '18',
        'composer_memory_limit' => '-1',
        'build_timeout' => 600, // 10 minutes
        'health_check_timeout' => 30,
        'health_check_retries' => 3,
    ],

    'ssl' => [
        'provider' => 'letsencrypt',
        'email' => env('SSL_EMAIL', 'admin@susankshakya.com.np'),
        'auto_renewal' => true,
        'renewal_cron' => '0 2 * * *', // Daily at 2 AM
    ],

    'backup' => [
        'enabled' => env('BACKUP_ENABLED', true),
        'directory' => env('BACKUP_DIRECTORY', '/var/backups/dokku'),
        'retention_days' => env('BACKUP_RETENTION_DAYS', 30),
        'schedule' => env('BACKUP_SCHEDULE', '0 3 * * 0'), // Weekly on Sunday at 3 AM
    ],

    'git' => [
        'remotes' => [
            'production' => 'dokku@209.50.227.94:restant-main',
            'staging' => 'dokku@209.50.227.94:restant-staging',
        ],
        'deploy_branch' => [
            'production' => 'main',
            'staging' => 'staging',
        ],
    ],

    'laravel' => [
        'config_cache' => true,
        'route_cache' => true,
        'view_cache' => true,
        'optimize_autoloader' => true,
        'migrate_on_deploy' => true,
        'seed_on_deploy' => false,
    ],

    'notifications' => [
        'channels' => [
            'slack' => [
                'enabled' => env('SLACK_NOTIFICATIONS_ENABLED', false),
                'webhook_url' => env('SLACK_WEBHOOK_URL'),
                'channel' => env('SLACK_CHANNEL', '#deployments'),
            ],
            'email' => [
                'enabled' => env('EMAIL_NOTIFICATIONS_ENABLED', true),
                'recipients' => explode(',', env('DEPLOYMENT_EMAIL_RECIPIENTS', 'admin@susankshakya.com.np')),
            ],
        ],
        'events' => [
            'deployment_started' => true,
            'deployment_success' => true,
            'deployment_failed' => true,
            'rollback_triggered' => true,
            'ssl_renewal' => false,
            'backup_completed' => false,
        ],
    ],
];