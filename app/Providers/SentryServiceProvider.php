<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use function Sentry\configureScope;
use function Sentry\init;

class SentryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Sentry configuration based on deployment environment
        $this->app->singleton('sentry.config', function () {
            return $this->buildSentryConfig();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (!$this->shouldEnableSentry()) {
            return;
        }

        $this->configureSentry();
        $this->setupMultiTenantContext();
        $this->setupPerformanceMonitoring();
        $this->initializePerformanceServices();
    }

    /**
     * Check if Sentry should be enabled.
     */
    protected function shouldEnableSentry(): bool
    {
        return config('deployment.monitoring.sentry.enabled', false) && 
               !empty(config('sentry.dsn'));
    }

    /**
     * Build Sentry configuration based on deployment environment.
     */
    protected function buildSentryConfig(): array
    {
        $deploymentEnv = $this->app->make('deployment.environment');
        $baseConfig = config('sentry');
        
        // Override environment with deployment environment
        $baseConfig['environment'] = $deploymentEnv;
        
        // Set traces sample rate from deployment config
        $baseConfig['traces_sample_rate'] = config('deployment.monitoring.sentry.traces_sample_rate', 0.1);
        
        return $baseConfig;
    }

    /**
     * Configure Sentry with deployment-aware settings.
     */
    protected function configureSentry(): void
    {
        $config = $this->app->make('sentry.config');
        
        // Initialize Sentry with our custom configuration
        init([
            'dsn' => $config['dsn'],
            'environment' => $config['environment'],
            'release' => $config['release'],
            'sample_rate' => $config['sample_rate'],
            'traces_sample_rate' => $config['traces_sample_rate'],
            'send_default_pii' => $config['send_default_pii'],
            'context_lines' => $config['context_lines'],
            'integrations' => [
                new Integration(),
            ],
            'before_send' => function (\Sentry\Event $event): ?\Sentry\Event {
                return $this->beforeSendCallback($event);
            },
        ]);
    }

    /**
     * Setup multi-tenant context for Sentry.
     */
    protected function setupMultiTenantContext(): void
    {
        if (!config('sentry.multi_tenant.enabled', true)) {
            return;
        }

        configureScope(function (Scope $scope): void {
            // Add deployment environment tag
            if (config('sentry.multi_tenant.environment_tag_enabled', true)) {
                $deploymentEnv = $this->app->make('deployment.environment');
                $scope->setTag('deployment_environment', $deploymentEnv);
            }

            // Add application type context
            $scope->setTag('app_type', config('app.projecttype', 'ft'));
            $scope->setTag('is_qrsaas', config('app.isqrsaas', false) ? 'true' : 'false');
            $scope->setTag('is_whatsapp', config('app.iswp', false) ? 'true' : 'false');
            $scope->setTag('is_pos_cloud', config('app.ispc', false) ? 'true' : 'false');

            // Add request context if available
            if (request()) {
                $scope->setTag('subdomain', $this->extractSubdomain());
                $scope->setContext('request', [
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            // Add tenant identification if user is authenticated
            $this->addTenantContext($scope);
        });
    }

    /**
     * Setup performance monitoring configuration.
     */
    protected function setupPerformanceMonitoring(): void
    {
        // Add custom performance monitoring tags
        configureScope(function (Scope $scope): void {
            $scope->setTag('laravel_version', app()->version());
            $scope->setTag('php_version', PHP_VERSION);
        });
    }

    /**
     * Add tenant-specific context to Sentry scope.
     */
    protected function addTenantContext(Scope $scope): void
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();
                
                // Set user context
                $scope->setUser([
                    'id' => $user->id,
                    'email' => $user->email ?? null,
                ]);

                // Add tenant identification
                $tenantTag = config('sentry.multi_tenant.tenant_tag', 'tenant_id');
                
                // Try to identify tenant from user or request
                $tenantId = $this->identifyTenant($user);
                if ($tenantId) {
                    $scope->setTag($tenantTag, $tenantId);
                }

                // Add user role context if available
                if (method_exists($user, 'getRoleNames')) {
                    $scope->setTag('user_roles', implode(',', $user->getRoleNames()->toArray()));
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the application
            Log::warning('Failed to add tenant context to Sentry', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Identify tenant from user or request context.
     */
    protected function identifyTenant($user): ?string
    {
        // Method 1: Check if user has a tenant_id property
        if (isset($user->tenant_id)) {
            return (string) $user->tenant_id;
        }

        // Method 2: Extract from subdomain
        $subdomain = $this->extractSubdomain();
        if ($subdomain && $subdomain !== 'www') {
            return $subdomain;
        }

        // Method 3: Check if user belongs to a restaurant (for this specific app)
        if (isset($user->restaurant_id)) {
            return "restaurant_{$user->restaurant_id}";
        }

        return null;
    }

    /**
     * Extract subdomain from current request.
     */
    protected function extractSubdomain(): ?string
    {
        if (!request()) {
            return null;
        }

        $host = request()->getHost();
        $baseDomain = config('deployment.subdomain.base_domain', 'susankshakya.com.np');
        
        // Remove base domain to get subdomain part
        if (str_ends_with($host, $baseDomain)) {
            $subdomain = str_replace('.' . $baseDomain, '', $host);
            
            // Remove app prefix if present
            $appPrefix = config('deployment.subdomain.app_prefix', 'restant');
            if (str_starts_with($subdomain, $appPrefix . '.')) {
                return substr($subdomain, strlen($appPrefix) + 1);
            }
            
            return $subdomain;
        }

        return null;
    }

    /**
     * Initialize performance monitoring services.
     */
    protected function initializePerformanceServices(): void
    {
        // Initialize performance monitoring service
        $performanceService = app(\App\Services\SentryPerformanceService::class);
        
        // Setup database monitoring if enabled
        if (config('sentry.performance_monitoring.database_monitoring', true)) {
            $performanceService->setupDatabaseMonitoring();
        }
    }

    /**
     * Before send callback to filter and modify events.
     */
    protected function beforeSendCallback(\Sentry\Event $event): ?\Sentry\Event
    {
        // Filter out certain exceptions in non-production environments
        $deploymentEnv = $this->app->make('deployment.environment');
        
        if ($deploymentEnv !== 'production') {
            // Don't send certain types of errors in staging/development
            $exception = $event->getExceptions()[0] ?? null;
            if ($exception) {
                $exceptionClass = $exception->getType();
                
                // Skip common development errors
                $skipInNonProd = [
                    'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
                    'Illuminate\Database\QueryException', // Only in development
                ];
                
                if (in_array($exceptionClass, $skipInNonProd) && $deploymentEnv === 'development') {
                    return null;
                }
            }
        }

        // Add additional context
        $event->setTag('deployment_environment', $deploymentEnv);
        
        return $event;
    }
}