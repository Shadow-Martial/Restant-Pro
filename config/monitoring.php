<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for all monitoring integrations
    | including Sentry, Flagsmith, and Grafana Cloud.
    |
    */

    'sentry' => [
        'enabled' => env('SENTRY_ENABLED', true),
        'dsn' => env('SENTRY_LARAVEL_DSN'),
        'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
        'environment' => env('APP_ENV', 'production'),
        'release' => env('APP_VERSION', '1.0.0'),
    ],

    'flagsmith' => [
        'enabled' => env('FLAGSMITH_ENABLED', true),
        'environment_key' => env('FLAGSMITH_ENVIRONMENT_KEY'),
        'api_url' => env('FLAGSMITH_API_URL', 'https://flagsmith.susankshakya.com.np/api/v1/'),
        'timeout' => env('FLAGSMITH_TIMEOUT', 5),
        'cache_ttl' => env('FLAGSMITH_CACHE_TTL', 300), // 5 minutes
    ],

    'grafana' => [
        'enabled' => env('GRAFANA_ENABLED', true),
        'instance_id' => env('GRAFANA_CLOUD_INSTANCE_ID'),
        'api_key' => env('GRAFANA_CLOUD_API_KEY'),
        
        // Metrics configuration
        'metrics' => [
            'enabled' => env('GRAFANA_METRICS_ENABLED', true),
            'endpoint' => env('GRAFANA_METRICS_ENDPOINT', 'https://prometheus-prod-01-eu-west-0.grafana.net/api/prom/push'),
            'batch_size' => env('GRAFANA_METRICS_BATCH_SIZE', 100),
            'flush_interval' => env('GRAFANA_METRICS_FLUSH_INTERVAL', 30), // seconds
        ],

        // Logs configuration
        'logs' => [
            'enabled' => env('GRAFANA_LOGS_ENABLED', true),
            'endpoint' => env('GRAFANA_LOGS_ENDPOINT', 'https://logs-prod-eu-west-0.grafana.net/loki/api/v1/push'),
            'batch_size' => env('GRAFANA_LOGS_BATCH_SIZE', 50),
            'flush_interval' => env('GRAFANA_LOGS_FLUSH_INTERVAL', 10), // seconds
            'levels' => ['error', 'warning', 'info'], // Log levels to send
        ],

        // Infrastructure monitoring
        'infrastructure' => [
            'enabled' => env('GRAFANA_INFRASTRUCTURE_ENABLED', true),
            'collect_interval' => env('GRAFANA_INFRASTRUCTURE_INTERVAL', 60), // seconds
            'metrics' => [
                'memory_usage' => true,
                'cpu_usage' => false, // Requires additional system tools
                'disk_usage' => false, // Requires additional system tools
                'database_connections' => true,
                'cache_metrics' => true,
                'queue_metrics' => true,
            ],
        ],

        // Application Performance Monitoring
        'apm' => [
            'enabled' => env('GRAFANA_APM_ENABLED', true),
            'track_requests' => true,
            'track_database_queries' => true,
            'track_cache_operations' => true,
            'track_queue_jobs' => true,
            'slow_request_threshold' => env('GRAFANA_SLOW_REQUEST_THRESHOLD', 1.0), // seconds
            'high_query_threshold' => env('GRAFANA_HIGH_QUERY_THRESHOLD', 10), // query count
        ],

        // Alerting configuration
        'alerts' => [
            'enabled' => env('GRAFANA_ALERTS_ENABLED', true),
            'thresholds' => [
                'response_time_p95' => env('GRAFANA_ALERT_RESPONSE_TIME_P95', 2.0), // seconds
                'error_rate' => env('GRAFANA_ALERT_ERROR_RATE', 0.05), // 5%
                'memory_usage' => env('GRAFANA_ALERT_MEMORY_USAGE', 0.8), // 80%
                'database_connections' => env('GRAFANA_ALERT_DB_CONNECTIONS', 80), // connection count
            ],
        ],

        // Labels to add to all metrics
        'default_labels' => [
            'app' => env('APP_NAME', 'laravel'),
            'environment' => env('APP_ENV', 'production'),
            'version' => env('APP_VERSION', '1.0.0'),
            'server' => env('SERVER_NAME', gethostname()),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Tracking
    |--------------------------------------------------------------------------
    |
    | Configuration for performance tracking across the application.
    |
    */

    'performance' => [
        'enabled' => env('PERFORMANCE_TRACKING_ENABLED', true),
        'sample_rate' => env('PERFORMANCE_SAMPLE_RATE', 1.0), // Track 100% of requests
        'track_memory' => true,
        'track_queries' => true,
        'track_cache' => true,
        'track_external_requests' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Checks
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring service health checks.
    |
    */

    'health_checks' => [
        'enabled' => env('HEALTH_CHECKS_ENABLED', true),
        'interval' => env('HEALTH_CHECK_INTERVAL', 300), // 5 minutes
        'services' => [
            'sentry' => true,
            'flagsmith' => true,
            'grafana' => true,
            'database' => true,
            'cache' => true,
            'queue' => true,
        ],
    ],
];