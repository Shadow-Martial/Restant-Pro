<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Grafana Cloud Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Grafana Cloud integration including logs and metrics.
    |
    */

    'enabled' => env('GRAFANA_ENABLED', true),

    'api_key' => env('GRAFANA_CLOUD_API_KEY', 'glc_eyJvIjoiMTUyMjU1OSIsIm4iOiJyZXN0YW50LXByby1yZXN0YW50LXBybyIsImsiOiI5VUU1OFZEODhwWVpUQTNjN0E3UWNrMjYiLCJtIjp7InIiOiJ1cyJ9fQ=='),

    'instance_id' => env('GRAFANA_CLOUD_INSTANCE_ID', '1320535'),

    /*
    |--------------------------------------------------------------------------
    | Grafana Logs Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Grafana Cloud Logs (Loki) integration.
    |
    */

    'logs' => [
        'enabled' => env('GRAFANA_LOGS_ENABLED', true),
        'url' => env('GRAFANA_LOGS_URL', 'https://logs-prod-028.grafana.net'),
        'user' => env('GRAFANA_LOGS_USER', '1320535'),
        'password' => env('GRAFANA_LOGS_PASSWORD', 'glc_eyJvIjoiMTUyMjU1OSIsIm4iOiJzdGFjay0xMzYyNzQxLWhsLXJlYWQtc3RhY2siLCJrIjoiczhYZzRRdXRGNUYySVhsOTEzTDdaQzE1IiwibSI6eyJyIjoicHJvZC1hcC1zb3V0aC0xIn19'),
        'datasource_name' => env('GRAFANA_LOGS_DATASOURCE', 'grafanacloud-shadowmartial-logs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Grafana Metrics Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Grafana Cloud Metrics (Prometheus) integration.
    |
    */

    'metrics' => [
        'enabled' => env('GRAFANA_METRICS_ENABLED', true),
        'url' => env('GRAFANA_METRICS_URL', 'https://prometheus-prod-01-us-central-0.grafana.net/api/prom/push'),
        'user' => env('GRAFANA_METRICS_USER', '1320535'),
        'password' => env('GRAFANA_METRICS_PASSWORD', 'glc_eyJvIjoiMTUyMjU1OSIsIm4iOiJyZXN0YW50LXByby1yZXN0YW50LXBybyIsImsiOiI5VUU1OFZEODhwWVpUQTNjN0E3UWNrMjYiLCJtIjp7InIiOiJ1cyJ9fQ=='),
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Metrics
    |--------------------------------------------------------------------------
    |
    | Configuration for application-specific metrics collection.
    |
    */

    'application_metrics' => [
        'enabled' => env('GRAFANA_APP_METRICS_ENABLED', true),
        'prefix' => env('GRAFANA_METRICS_PREFIX', 'restant'),
        'labels' => [
            'environment' => env('APP_ENV', 'production'),
            'application' => env('APP_NAME', 'restant'),
            'version' => env('APP_VERSION', '1.0.0'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Deployment Metrics
    |--------------------------------------------------------------------------
    |
    | Configuration for deployment-specific metrics and logging.
    |
    */

    'deployment_metrics' => [
        'enabled' => env('GRAFANA_DEPLOYMENT_METRICS_ENABLED', true),
        'track_deployments' => true,
        'track_rollbacks' => true,
        'track_health_checks' => true,
        'track_performance' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Grafana alerting and notifications.
    |
    */

    'alerting' => [
        'enabled' => env('GRAFANA_ALERTING_ENABLED', true),
        'webhook_url' => env('GRAFANA_ALERT_WEBHOOK_URL'),
        'notification_channels' => [
            'slack' => env('GRAFANA_SLACK_WEBHOOK'),
            'email' => env('GRAFANA_ALERT_EMAIL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Retention
    |--------------------------------------------------------------------------
    |
    | Configuration for data retention policies.
    |
    */

    'retention' => [
        'logs_days' => env('GRAFANA_LOGS_RETENTION_DAYS', 30),
        'metrics_days' => env('GRAFANA_METRICS_RETENTION_DAYS', 90),
        'traces_days' => env('GRAFANA_TRACES_RETENTION_DAYS', 7),
    ],
];