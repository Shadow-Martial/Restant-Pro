<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for deployment health checks and
    | monitoring service integrations.
    |
    */

    'enabled' => env('HEALTH_CHECKS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Critical Services
    |--------------------------------------------------------------------------
    |
    | These services must be healthy for the application to be considered
    | ready for deployment. If any of these fail, deployment should be aborted.
    |
    */
    'critical_services' => [
        'database',
        'cache',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Services
    |--------------------------------------------------------------------------
    |
    | These services can be degraded without preventing deployment, but
    | their status should be monitored and reported.
    |
    */
    'optional_services' => [
        'sentry',
        'flagsmith',
        'grafana',
        'ssl',
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Timeouts
    |--------------------------------------------------------------------------
    |
    | Timeout values for various health check operations (in seconds).
    |
    */
    'timeouts' => [
        'database' => env('HEALTH_CHECK_DB_TIMEOUT', 5),
        'cache' => env('HEALTH_CHECK_CACHE_TIMEOUT', 3),
        'sentry' => env('HEALTH_CHECK_SENTRY_TIMEOUT', 10),
        'flagsmith' => env('HEALTH_CHECK_FLAGSMITH_TIMEOUT', 10),
        'grafana' => env('HEALTH_CHECK_GRAFANA_TIMEOUT', 10),
        'ssl' => env('HEALTH_CHECK_SSL_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | SSL Certificate Validation
    |--------------------------------------------------------------------------
    |
    | Configuration for SSL certificate health checks.
    |
    */
    'ssl' => [
        'enabled' => env('HEALTH_CHECK_SSL_ENABLED', true),
        'warning_days' => env('HEALTH_CHECK_SSL_WARNING_DAYS', 30),
        'critical_days' => env('HEALTH_CHECK_SSL_CRITICAL_DAYS', 7),
        'skip_local' => env('HEALTH_CHECK_SSL_SKIP_LOCAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for sending health check results to monitoring services.
    |
    */
    'monitoring' => [
        'send_to_sentry' => env('HEALTH_CHECK_SEND_TO_SENTRY', false),
        'send_to_grafana' => env('HEALTH_CHECK_SEND_TO_GRAFANA', true),
        'log_failures' => env('HEALTH_CHECK_LOG_FAILURES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Verification
    |--------------------------------------------------------------------------
    |
    | Settings specific to deployment health verification.
    |
    */
    'deployment' => [
        'verify_migrations' => env('HEALTH_CHECK_VERIFY_MIGRATIONS', true),
        'verify_cache_clear' => env('HEALTH_CHECK_VERIFY_CACHE_CLEAR', true),
        'verify_config_cache' => env('HEALTH_CHECK_VERIFY_CONFIG_CACHE', true),
        'max_deployment_time' => env('HEALTH_CHECK_MAX_DEPLOYMENT_TIME', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for circuit breaker pattern in health checks.
    |
    */
    'circuit_breaker' => [
        'failure_threshold' => env('HEALTH_CHECK_FAILURE_THRESHOLD', 5),
        'recovery_timeout' => env('HEALTH_CHECK_RECOVERY_TIMEOUT', 60), // seconds
        'half_open_max_calls' => env('HEALTH_CHECK_HALF_OPEN_MAX_CALLS', 3),
    ],
];