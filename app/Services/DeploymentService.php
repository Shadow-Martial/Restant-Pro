<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class DeploymentService
{
    /**
     * Get the current deployment environment
     */
    public function getCurrentEnvironment(): string
    {
        return config('app.env', 'production');
    }

    /**
     * Get deployment configuration for current environment
     */
    public function getEnvironmentConfig(): array
    {
        $environment = $this->getCurrentEnvironment();
        
        return config("dokku-deployment.environments.{$environment}", []);
    }

    /**
     * Check if we're running in a Dokku environment
     */
    public function isDokkuEnvironment(): bool
    {
        return !empty(env('DOKKU_APP_NAME')) || !empty(env('DATABASE_URL'));
    }

    /**
     * Get the current app name
     */
    public function getAppName(): string
    {
        return env('DOKKU_APP_NAME', $this->getEnvironmentConfig()['app_name'] ?? 'unknown');
    }

    /**
     * Get the current domain
     */
    public function getDomain(): string
    {
        return $this->getEnvironmentConfig()['domain'] ?? config('app.url');
    }

    /**
     * Check if SSL is enabled for current environment
     */
    public function isSslEnabled(): bool
    {
        return $this->getEnvironmentConfig()['ssl_enabled'] ?? true;
    }

    /**
     * Get monitoring service configuration
     */
    public function getMonitoringConfig(string $service): array
    {
        return config("dokku-deployment.monitoring.{$service}", []);
    }

    /**
     * Check if a monitoring service is enabled
     */
    public function isMonitoringEnabled(string $service): bool
    {
        return $this->getMonitoringConfig($service)['enabled'] ?? false;
    }

    /**
     * Get Sentry configuration
     */
    public function getSentryConfig(): array
    {
        return $this->getMonitoringConfig('sentry');
    }

    /**
     * Get Flagsmith configuration
     */
    public function getFlagsmithConfig(): array
    {
        return $this->getMonitoringConfig('flagsmith');
    }

    /**
     * Get Grafana configuration
     */
    public function getGrafanaConfig(): array
    {
        return $this->getMonitoringConfig('grafana');
    }

    /**
     * Get service configuration
     */
    public function getServiceConfig(string $service): array
    {
        return config("dokku-deployment.services.{$service}", []);
    }

    /**
     * Get deployment configuration
     */
    public function getDeploymentConfig(): array
    {
        return config('dokku-deployment.deployment', []);
    }

    /**
     * Log deployment event
     */
    public function logDeploymentEvent(string $event, array $context = []): void
    {
        $logContext = array_merge([
            'environment' => $this->getCurrentEnvironment(),
            'app_name' => $this->getAppName(),
            'domain' => $this->getDomain(),
            'timestamp' => now()->toISOString(),
        ], $context);

        Log::info("Deployment Event: {$event}", $logContext);
    }

    /**
     * Get health check configuration
     */
    public function getHealthCheckConfig(): array
    {
        $deploymentConfig = $this->getDeploymentConfig();
        
        return [
            'timeout' => $deploymentConfig['health_check_timeout'] ?? 30,
            'retries' => $deploymentConfig['health_check_retries'] ?? 3,
        ];
    }

    /**
     * Perform basic health checks
     */
    public function performHealthCheck(): array
    {
        $results = [
            'status' => 'healthy',
            'checks' => [],
            'timestamp' => now()->toISOString(),
        ];

        // Database connectivity check
        try {
            \DB::connection()->getPdo();
            $results['checks']['database'] = 'healthy';
        } catch (\Exception $e) {
            $results['checks']['database'] = 'unhealthy';
            $results['status'] = 'unhealthy';
            Log::error('Database health check failed', ['error' => $e->getMessage()]);
        }

        // Redis connectivity check (if configured)
        if (config('cache.default') === 'redis') {
            try {
                \Cache::store('redis')->put('health_check', 'ok', 10);
                $results['checks']['redis'] = 'healthy';
            } catch (\Exception $e) {
                $results['checks']['redis'] = 'unhealthy';
                $results['status'] = 'unhealthy';
                Log::error('Redis health check failed', ['error' => $e->getMessage()]);
            }
        }

        // Sentry connectivity check (if enabled)
        if ($this->isMonitoringEnabled('sentry')) {
            try {
                // Test Sentry connection by capturing a test message
                \Sentry\captureMessage('Health check test', \Sentry\Severity::info());
                $results['checks']['sentry'] = 'healthy';
            } catch (\Exception $e) {
                $results['checks']['sentry'] = 'unhealthy';
                Log::warning('Sentry health check failed', ['error' => $e->getMessage()]);
            }
        }

        // Flagsmith connectivity check (if enabled)
        if ($this->isMonitoringEnabled('flagsmith')) {
            $flagsmithConfig = $this->getFlagsmithConfig();
            if (!empty($flagsmithConfig['environment_key'])) {
                try {
                    // Test Flagsmith connection
                    $client = new \Flagsmith\Flagsmith($flagsmithConfig['environment_key']);
                    $client->getEnvironmentFlags();
                    $results['checks']['flagsmith'] = 'healthy';
                } catch (\Exception $e) {
                    $results['checks']['flagsmith'] = 'unhealthy';
                    Log::warning('Flagsmith health check failed', ['error' => $e->getMessage()]);
                }
            }
        }

        return $results;
    }

    /**
     * Get deployment information
     */
    public function getDeploymentInfo(): array
    {
        return [
            'environment' => $this->getCurrentEnvironment(),
            'app_name' => $this->getAppName(),
            'domain' => $this->getDomain(),
            'ssl_enabled' => $this->isSslEnabled(),
            'dokku_environment' => $this->isDokkuEnvironment(),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'monitoring' => [
                'sentry' => $this->isMonitoringEnabled('sentry'),
                'flagsmith' => $this->isMonitoringEnabled('flagsmith'),
                'grafana' => $this->isMonitoringEnabled('grafana'),
            ],
            'deployment_time' => env('DEPLOYMENT_TIME', 'unknown'),
            'git_commit' => env('GIT_COMMIT', 'unknown'),
        ];
    }
}