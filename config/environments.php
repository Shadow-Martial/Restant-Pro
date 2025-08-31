<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Environment-Specific Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file manages environment-specific settings and
    | provides a centralized way to handle different deployment environments.
    |
    */

    'current' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Production Environment Configuration
    |--------------------------------------------------------------------------
    */
    'production' => [
        'app' => [
            'debug' => false,
            'log_level' => 'error',
            'url' => env('APP_URL', 'https://restant.main.susankshakya.com.np'),
        ],
        'database' => [
            'connections' => [
                'mysql' => [
                    'strict' => true,
                    'engine' => 'InnoDB',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
            ],
        ],
        'cache' => [
            'default' => 'redis',
            'ttl' => 3600, // 1 hour
        ],
        'session' => [
            'driver' => 'redis',
            'lifetime' => 120, // 2 hours
            'secure' => true,
            'same_site' => 'strict',
        ],
        'monitoring' => [
            'sentry' => [
                'enabled' => true,
                'traces_sample_rate' => 0.1,
                'profiles_sample_rate' => 0.1,
            ],
            'flagsmith' => [
                'enabled' => true,
                'cache_ttl' => 300, // 5 minutes
            ],
            'grafana' => [
                'enabled' => true,
                'metrics_interval' => 60, // 1 minute
            ],
        ],
        'security' => [
            'force_https' => true,
            'hsts_max_age' => 31536000, // 1 year
            'content_security_policy' => true,
        ],
        'performance' => [
            'opcache_enabled' => true,
            'view_cache' => true,
            'config_cache' => true,
            'route_cache' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Staging Environment Configuration
    |--------------------------------------------------------------------------
    */
    'staging' => [
        'app' => [
            'debug' => true,
            'log_level' => 'debug',
            'url' => env('APP_URL', 'https://restant.staging.susankshakya.com.np'),
        ],
        'database' => [
            'connections' => [
                'mysql' => [
                    'strict' => false,
                    'engine' => 'InnoDB',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
            ],
        ],
        'cache' => [
            'default' => 'redis',
            'ttl' => 300, // 5 minutes
        ],
        'session' => [
            'driver' => 'redis',
            'lifetime' => 60, // 1 hour
            'secure' => true,
            'same_site' => 'lax',
        ],
        'monitoring' => [
            'sentry' => [
                'enabled' => true,
                'traces_sample_rate' => 1.0, // Full tracing in staging
                'profiles_sample_rate' => 1.0,
            ],
            'flagsmith' => [
                'enabled' => true,
                'cache_ttl' => 60, // 1 minute for faster testing
            ],
            'grafana' => [
                'enabled' => true,
                'metrics_interval' => 30, // 30 seconds
            ],
        ],
        'security' => [
            'force_https' => true,
            'hsts_max_age' => 0, // Disabled for staging
            'content_security_policy' => false,
        ],
        'performance' => [
            'opcache_enabled' => false,
            'view_cache' => false,
            'config_cache' => false,
            'route_cache' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing Environment Configuration
    |--------------------------------------------------------------------------
    */
    'testing' => [
        'app' => [
            'debug' => true,
            'log_level' => 'debug',
            'url' => 'http://localhost',
        ],
        'database' => [
            'default' => 'sqlite',
            'connections' => [
                'sqlite' => [
                    'database' => ':memory:',
                ],
            ],
        ],
        'cache' => [
            'default' => 'array',
            'ttl' => 60,
        ],
        'session' => [
            'driver' => 'array',
            'lifetime' => 120,
            'secure' => false,
        ],
        'monitoring' => [
            'sentry' => [
                'enabled' => false,
            ],
            'flagsmith' => [
                'enabled' => false,
            ],
            'grafana' => [
                'enabled' => false,
            ],
        ],
        'security' => [
            'force_https' => false,
            'hsts_max_age' => 0,
            'content_security_policy' => false,
        ],
        'performance' => [
            'opcache_enabled' => false,
            'view_cache' => false,
            'config_cache' => false,
            'route_cache' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Development Environment Configuration
    |--------------------------------------------------------------------------
    */
    'local' => [
        'app' => [
            'debug' => true,
            'log_level' => 'debug',
            'url' => env('APP_URL', 'http://localhost:8000'),
        ],
        'database' => [
            'connections' => [
                'mysql' => [
                    'strict' => false,
                    'engine' => 'InnoDB',
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                ],
            ],
        ],
        'cache' => [
            'default' => 'file',
            'ttl' => 60,
        ],
        'session' => [
            'driver' => 'file',
            'lifetime' => 120,
            'secure' => false,
            'same_site' => 'lax',
        ],
        'monitoring' => [
            'sentry' => [
                'enabled' => false,
            ],
            'flagsmith' => [
                'enabled' => false,
            ],
            'grafana' => [
                'enabled' => false,
            ],
        ],
        'security' => [
            'force_https' => false,
            'hsts_max_age' => 0,
            'content_security_policy' => false,
        ],
        'performance' => [
            'opcache_enabled' => false,
            'view_cache' => false,
            'config_cache' => false,
            'route_cache' => false,
        ],
    ],
];