<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sentry DSN
    |--------------------------------------------------------------------------
    |
    | The DSN tells the SDK where to send the events to. If this value is not
    | provided, the SDK will try to read it from the SENTRY_LARAVEL_DSN
    | environment variable. If that variable also does not exist, the SDK
    | will just not send any events.
    |
    */

    'dsn' => env('SENTRY_LARAVEL_DSN', 'https://eb01fe83d3662dd65aee15a185d4308c@o4509937918738432.ingest.de.sentry.io/4509938290327632'),

    /*
    |--------------------------------------------------------------------------
    | Sentry Release
    |--------------------------------------------------------------------------
    |
    | This value is used to identify the release of your application. This
    | can be a git SHA, a version number, or any other string that uniquely
    | identifies a release.
    |
    */

    'release' => env('SENTRY_RELEASE'),

    /*
    |--------------------------------------------------------------------------
    | Sentry Environment
    |--------------------------------------------------------------------------
    |
    | This value is used to identify the environment your application is
    | running in. This can be production, staging, development, etc.
    |
    */

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Sample Rate
    |--------------------------------------------------------------------------
    |
    | This value controls the percentage of events that are sent to Sentry.
    | For example, to send 25% of events, set this to 0.25.
    |
    */

    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Traces Sample Rate
    |--------------------------------------------------------------------------
    |
    | This value controls the percentage of transactions that are sent to
    | Sentry for performance monitoring. Set to 0.0 to disable performance
    | monitoring, or to 1.0 to send all transactions.
    |
    */

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),

    /*
    |--------------------------------------------------------------------------
    | Profiles Sample Rate
    |--------------------------------------------------------------------------
    |
    | This value controls the percentage of transactions that are profiled.
    | Profiling is only available when traces_sample_rate is greater than 0.
    |
    */

    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),

    /*
    |--------------------------------------------------------------------------
    | Send Default PII
    |--------------------------------------------------------------------------
    |
    | If this flag is enabled, certain personally identifiable information
    | (PII) is added by active integrations. By default, no such data is sent.
    |
    */

    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    /*
    |--------------------------------------------------------------------------
    | Capture Unhandled Promise Rejections
    |--------------------------------------------------------------------------
    |
    | This flag controls whether the SDK should capture unhandled promise
    | rejections. This is only relevant for JavaScript environments.
    |
    */

    'capture_unhandled_rejections' => env('SENTRY_CAPTURE_UNHANDLED_REJECTIONS', true),

    /*
    |--------------------------------------------------------------------------
    | Context Lines
    |--------------------------------------------------------------------------
    |
    | The number of lines of code context to capture around the line that
    | caused an error. Set to 0 to disable source code context.
    |
    */

    'context_lines' => 5,

    /*
    |--------------------------------------------------------------------------
    | Breadcrumbs
    |--------------------------------------------------------------------------
    |
    | Configuration for breadcrumbs that help provide context for errors.
    |
    */

    'breadcrumbs' => [
        // Capture SQL queries as breadcrumbs
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES', true),

        // Capture SQL bindings in breadcrumbs
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS', false),

        // Capture queue job information
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO', true),

        // Capture command information
        'command_info' => env('SENTRY_BREADCRUMBS_COMMAND_INFO', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrations
    |--------------------------------------------------------------------------
    |
    | Configuration for Sentry integrations.
    |
    */

    'integrations' => [
        \Sentry\Laravel\Integration::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Before Send Callback
    |--------------------------------------------------------------------------
    |
    | This callback is called before an event is sent to Sentry. You can use
    | this to filter out events or modify them before they are sent.
    |
    */

    'before_send' => null,

    /*
    |--------------------------------------------------------------------------
    | Before Send Transaction Callback
    |--------------------------------------------------------------------------
    |
    | This callback is called before a transaction is sent to Sentry. You can
    | use this to filter out transactions or modify them before they are sent.
    |
    */

    'before_send_transaction' => null,

    /*
    |--------------------------------------------------------------------------
    | Multi-tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration specific to multi-tenant applications.
    |
    */

    'multi_tenant' => [
        // Enable tenant identification in error reports
        'enabled' => env('SENTRY_MULTI_TENANT_ENABLED', true),

        // Tag name for tenant identification
        'tenant_tag' => 'tenant_id',

        // User context for tenant identification
        'include_tenant_in_user' => true,

        // Environment tag based on deployment environment
        'environment_tag_enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for performance monitoring and thresholds.
    |
    */

    'performance_monitoring' => [
        // Enable automatic database query monitoring
        'database_monitoring' => env('SENTRY_DATABASE_MONITORING', true),

        // Enable HTTP request monitoring
        'http_monitoring' => env('SENTRY_HTTP_MONITORING', true),

        // Enable cache operation monitoring
        'cache_monitoring' => env('SENTRY_CACHE_MONITORING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for detecting performance issues (in milliseconds).
    |
    */

    'performance_thresholds' => [
        'database_query' => env('SENTRY_DB_THRESHOLD', 1000),
        'http_request' => env('SENTRY_HTTP_THRESHOLD', 5000),
        'cache_operation' => env('SENTRY_CACHE_THRESHOLD', 100),
        'file_operation' => env('SENTRY_FILE_THRESHOLD', 500),
    ],
];