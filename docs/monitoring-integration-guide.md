# Monitoring Service Integration Guide

## Overview

This guide covers the integration of monitoring services (Sentry, Flagsmith, and Grafana Cloud) with the Laravel application for comprehensive observability and feature management.

## Sentry Integration

### 1. Setup and Configuration

#### Install Sentry SDK
```bash
composer require sentry/sentry-laravel
php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
```

#### Configure Environment Variables
```bash
# Production
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_PROFILES_SAMPLE_RATE=0.1

# Staging  
SENTRY_LARAVEL_DSN=https://your-staging-dsn@sentry.io/staging-project-id
SENTRY_TRACES_SAMPLE_RATE=1.0
SENTRY_PROFILES_SAMPLE_RATE=1.0
```

#### Update Configuration
```php
// config/sentry.php
return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),
    'release' => env('SENTRY_RELEASE', trim(exec('git log --pretty="%h" -n1 HEAD'))),
    'environment' => env('APP_ENV', 'production'),
    
    'breadcrumbs' => [
        'logs' => true,
        'cache' => true,
        'livewire' => true,
        'sql_queries' => env('APP_DEBUG', false),
        'sql_bindings' => env('APP_DEBUG', false),
        'queue_info' => true,
        'command_info' => true,
    ],
    
    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs' => true,
        'sql_queries' => true,
        'requests' => true,
        'redis_commands' => env('SENTRY_TRACE_REDIS_COMMANDS', false),
        'http_client_requests' => true,
    ],
    
    'send_default_pii' => false,
    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.0),
    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.0),
];
```

### 2. Custom Context and Tags

#### Multi-tenant Context
```php
// app/Http/Middleware/SentryContext.php
<?php

namespace App\Http\Middleware;

use Closure;
use Sentry\State\Scope;
use function Sentry\configureScope;

class SentryContext
{
    public function handle($request, Closure $next)
    {
        configureScope(function (Scope $scope): void {
            if (auth()->check()) {
                $scope->setUser([
                    'id' => auth()->id(),
                    'email' => auth()->user()->email,
                    'tenant' => auth()->user()->tenant_id ?? 'default',
                ]);
            }
            
            $scope->setTag('environment', app()->environment());
            $scope->setTag('server', gethostname());
            
            if ($tenant = request()->header('X-Tenant-ID')) {
                $scope->setTag('tenant', $tenant);
            }
        });

        return $next($request);
    }
}
```

### 3. Performance Monitoring

#### Custom Transactions
```php
// app/Services/SentryService.php
<?php

namespace App\Services;

use Sentry\Tracing\TransactionContext;
use function Sentry\startTransaction;

class SentryService
{
    public function trackOperation(string $operation, callable $callback)
    {
        $transactionContext = new TransactionContext();
        $transactionContext->setName($operation);
        $transactionContext->setOp('custom');
        
        $transaction = startTransaction($transactionContext);
        
        try {
            $result = $callback();
            $transaction->setStatus('ok');
            return $result;
        } catch (\Exception $e) {
            $transaction->setStatus('internal_error');
            throw $e;
        } finally {
            $transaction->finish();
        }
    }
}
```

## Flagsmith Integration

### 1. Setup and Configuration

#### Install Flagsmith Client
```bash
composer require flagsmith/flagsmith-php-client
```

#### Environment Configuration
```bash
FLAGSMITH_ENVIRONMENT_KEY=ser.your_environment_key_here
FLAGSMITH_API_URL=https://flagsmith.susankshakya.com.np/api/v1/
FLAGSMITH_ENABLE_LOCAL_EVALUATION=false
FLAGSMITH_CACHE_TTL=300
```

#### Service Provider
```php
// app/Providers/FlagsmithServiceProvider.php
<?php

namespace App\Providers;

use Flagsmith\Flagsmith;
use Illuminate\Support\ServiceProvider;

class FlagsmithServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Flagsmith::class, function ($app) {
            return new Flagsmith(
                environmentKey: config('services.flagsmith.environment_key'),
                apiUrl: config('services.flagsmith.api_url'),
                enableLocalEvaluation: config('services.flagsmith.enable_local_evaluation', false),
                environmentTtl: config('services.flagsmith.cache_ttl', 300),
            );
        });
    }
}
```

#### Configuration File
```php
// config/services.php
return [
    // Other services...
    
    'flagsmith' => [
        'environment_key' => env('FLAGSMITH_ENVIRONMENT_KEY'),
        'api_url' => env('FLAGSMITH_API_URL', 'https://edge.api.flagsmith.com/api/v1/'),
        'enable_local_evaluation' => env('FLAGSMITH_ENABLE_LOCAL_EVALUATION', false),
        'cache_ttl' => env('FLAGSMITH_CACHE_TTL', 300),
    ],
];
```

### 2. Feature Flag Helper

```php
// app/Helpers/FeatureFlags.php
<?php

namespace App\Helpers;

use Flagsmith\Flagsmith;
use Flagsmith\Models\Identity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FeatureFlags
{
    protected $flagsmith;
    
    public function __construct(Flagsmith $flagsmith)
    {
        $this->flagsmith = $flagsmith;
    }
    
    public function isEnabled(string $feature, $userId = null, array $traits = []): bool
    {
        try {
            $cacheKey = "feature_flag_{$feature}_" . ($userId ?? 'anonymous');
            
            return Cache::remember($cacheKey, 300, function () use ($feature, $userId, $traits) {
                if ($userId) {
                    $identity = new Identity(identifier: (string) $userId, traits: $traits);
                    $flags = $this->flagsmith->getIdentityFlags($identity);
                } else {
                    $flags = $this->flagsmith->getEnvironmentFlags();
                }
                
                return $flags->isFeatureEnabled($feature);
            });
        } catch (\Exception $e) {
            Log::warning("Flagsmith error for feature {$feature}: " . $e->getMessage());
            return $this->getDefaultValue($feature);
        }
    }
    
    public function getValue(string $feature, $userId = null, array $traits = [])
    {
        try {
            $cacheKey = "feature_value_{$feature}_" . ($userId ?? 'anonymous');
            
            return Cache::remember($cacheKey, 300, function () use ($feature, $userId, $traits) {
                if ($userId) {
                    $identity = new Identity(identifier: (string) $userId, traits: $traits);
                    $flags = $this->flagsmith->getIdentityFlags($identity);
                } else {
                    $flags = $this->flagsmith->getEnvironmentFlags();
                }
                
                return $flags->getFeatureValue($feature);
            });
        } catch (\Exception $e) {
            Log::warning("Flagsmith error for feature value {$feature}: " . $e->getMessage());
            return $this->getDefaultValue($feature);
        }
    }
    
    protected function getDefaultValue(string $feature)
    {
        $defaults = [
            'new_dashboard' => false,
            'payment_gateway_v2' => false,
            'advanced_analytics' => false,
            'beta_features' => false,
        ];
        
        return $defaults[$feature] ?? false;
    }
}
```

### 3. Middleware Integration

```php
// app/Http/Middleware/FeatureFlagMiddleware.php
<?php

namespace App\Http\Middleware;

use App\Helpers\FeatureFlags;
use Closure;
use Illuminate\Http\Request;

class FeatureFlagMiddleware
{
    protected $featureFlags;
    
    public function __construct(FeatureFlags $featureFlags)
    {
        $this->featureFlags = $featureFlags;
    }
    
    public function handle(Request $request, Closure $next, string $feature)
    {
        $userId = auth()->id();
        $traits = auth()->check() ? [
            'email' => auth()->user()->email,
            'plan' => auth()->user()->plan ?? 'free',
            'tenant' => auth()->user()->tenant_id ?? 'default',
        ] : [];
        
        if (!$this->featureFlags->isEnabled($feature, $userId, $traits)) {
            abort(404);
        }
        
        return $next($request);
    }
}
```

## Grafana Cloud Integration

### 1. Setup and Configuration

#### Environment Variables
```bash
GRAFANA_CLOUD_API_KEY=your_api_key_here
GRAFANA_CLOUD_INSTANCE_ID=your_instance_id
GRAFANA_CLOUD_METRICS_URL=https://prometheus-prod-01-eu-west-0.grafana.net/api/prom/push
GRAFANA_CLOUD_LOGS_URL=https://logs-prod-eu-west-0.grafana.net/loki/api/v1/push
```

#### Service Provider
```php
// app/Providers/GrafanaServiceProvider.php
<?php

namespace App\Providers;

use App\Services\GrafanaService;
use Illuminate\Support\ServiceProvider;

class GrafanaServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(GrafanaService::class, function ($app) {
            return new GrafanaService(
                apiKey: config('services.grafana.api_key'),
                instanceId: config('services.grafana.instance_id'),
                metricsUrl: config('services.grafana.metrics_url'),
                logsUrl: config('services.grafana.logs_url'),
            );
        });
    }
}
```

### 2. Custom Metrics Service

```php
// app/Services/GrafanaService.php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GrafanaService
{
    protected $apiKey;
    protected $instanceId;
    protected $metricsUrl;
    protected $logsUrl;
    
    public function __construct(string $apiKey, string $instanceId, string $metricsUrl, string $logsUrl)
    {
        $this->apiKey = $apiKey;
        $this->instanceId = $instanceId;
        $this->metricsUrl = $metricsUrl;
        $this->logsUrl = $logsUrl;
    }
    
    public function sendMetric(string $name, float $value, array $labels = [])
    {
        try {
            $metric = [
                'name' => $name,
                'type' => 'gauge',
                'help' => "Laravel application metric: {$name}",
                'metrics' => [
                    [
                        'labels' => array_merge([
                            'app' => config('app.name'),
                            'env' => config('app.env'),
                            'instance' => gethostname(),
                        ], $labels),
                        'value' => $value,
                        'timestamp' => now()->timestamp * 1000,
                    ]
                ]
            ];
            
            Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->instanceId . ':' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->metricsUrl, $metric);
            
        } catch (\Exception $e) {
            Log::warning("Failed to send metric to Grafana: " . $e->getMessage());
        }
    }
    
    public function sendLog(string $level, string $message, array $context = [])
    {
        try {
            $logEntry = [
                'streams' => [
                    [
                        'stream' => array_merge([
                            'app' => config('app.name'),
                            'env' => config('app.env'),
                            'level' => $level,
                            'instance' => gethostname(),
                        ], $context),
                        'values' => [
                            [
                                (string) (now()->timestamp * 1000000000), // nanoseconds
                                $message
                            ]
                        ]
                    ]
                ]
            ];
            
            Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->instanceId . ':' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->logsUrl, $logEntry);
            
        } catch (\Exception $e) {
            Log::warning("Failed to send log to Grafana: " . $e->getMessage());
        }
    }
}
```

### 3. Performance Monitoring Middleware

```php
// app/Http/Middleware/GrafanaMetrics.php
<?php

namespace App\Http\Middleware;

use App\Services\GrafanaService;
use Closure;
use Illuminate\Http\Request;

class GrafanaMetrics
{
    protected $grafana;
    
    public function __construct(GrafanaService $grafana)
    {
        $this->grafana = $grafana;
    }
    
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $duration = microtime(true) - $startTime;
        
        $this->grafana->sendMetric('http_request_duration_seconds', $duration, [
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? 'unknown',
            'status_code' => (string) $response->getStatusCode(),
        ]);
        
        $this->grafana->sendMetric('http_requests_total', 1, [
            'method' => $request->method(),
            'route' => $request->route()?->getName() ?? 'unknown',
            'status_code' => (string) $response->getStatusCode(),
        ]);
        
        return $response;
    }
}
```

## Integration Testing

### 1. Sentry Testing
```php
// Test error capture
throw new \Exception('Test Sentry integration');

// Test performance monitoring
\Sentry\startTransaction(['name' => 'test-transaction', 'op' => 'test']);
```

### 2. Flagsmith Testing
```php
// Test feature flags
$featureFlags = app(FeatureFlags::class);
$isEnabled = $featureFlags->isEnabled('test_feature', auth()->id());
```

### 3. Grafana Testing
```php
// Test metrics
$grafana = app(GrafanaService::class);
$grafana->sendMetric('test_metric', 1.0, ['test' => 'true']);
```

## Troubleshooting

### Common Issues

1. **Sentry DSN Issues**: Verify DSN format and project permissions
2. **Flagsmith Connection**: Check API URL and environment key
3. **Grafana Authentication**: Verify API key and instance ID format
4. **SSL Certificate Issues**: Ensure proper certificate chain for self-hosted services

### Debugging Commands

```bash
# Check Sentry configuration
php artisan config:show sentry

# Test Flagsmith connection
php artisan tinker
>>> app(\Flagsmith\Flagsmith::class)->getEnvironmentFlags()

# Verify Grafana connectivity
curl -H "Authorization: Bearer INSTANCE:API_KEY" https://prometheus-url/api/v1/query
```

## Best Practices

1. **Error Handling**: Always implement fallbacks for monitoring service failures
2. **Caching**: Cache feature flags and metrics to reduce API calls
3. **Rate Limiting**: Implement rate limiting for metric submissions
4. **Security**: Never expose API keys in client-side code
5. **Performance**: Use async queues for non-critical monitoring data

## Monitoring Dashboards

### Recommended Grafana Dashboards

1. **Application Performance**: Response times, throughput, error rates
2. **Infrastructure Metrics**: CPU, memory, disk usage
3. **Business Metrics**: User registrations, orders, revenue
4. **Error Tracking**: Error rates by service, error types
5. **Feature Flag Usage**: Flag evaluation counts, user segments

### Sentry Alerts

1. **Error Rate Threshold**: > 5% error rate in 5 minutes
2. **Performance Degradation**: > 2s average response time
3. **New Error Types**: First occurrence of new error
4. **High Volume Errors**: > 100 errors in 1 minute

## Next Steps

1. Set up custom dashboards in Grafana Cloud
2. Configure alerting rules and notification channels
3. Implement business-specific metrics
4. Set up log aggregation and analysis
5. Create automated reports and insights