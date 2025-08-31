<?php

namespace App\Providers;

use App\Services\EnvironmentManager;
use App\Services\SecretManager;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;

class EnvironmentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(EnvironmentManager::class, function ($app) {
            return new EnvironmentManager();
        });

        $this->app->singleton(SecretManager::class, function ($app) {
            return new SecretManager();
        });

        $this->app->alias(EnvironmentManager::class, 'environment.manager');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $environmentManager = $this->app->make(EnvironmentManager::class);
        $currentEnv = $environmentManager->getCurrentEnvironment();

        // Apply environment-specific configurations
        $this->applyEnvironmentConfig($currentEnv);

        // Validate environment configuration
        if ($this->app->environment(['production', 'staging'])) {
            $environmentManager->logEnvironmentStatus();
        }
    }

    /**
     * Apply environment-specific configuration
     */
    protected function applyEnvironmentConfig(string $environment): void
    {
        $envConfig = config("environments.{$environment}", []);

        if (empty($envConfig)) {
            return;
        }

        // Apply app configuration
        if (isset($envConfig['app'])) {
            foreach ($envConfig['app'] as $key => $value) {
                Config::set("app.{$key}", $value);
            }
        }

        // Apply database configuration
        if (isset($envConfig['database'])) {
            foreach ($envConfig['database'] as $key => $value) {
                if ($key === 'connections') {
                    foreach ($value as $connection => $connectionConfig) {
                        foreach ($connectionConfig as $configKey => $configValue) {
                            Config::set("database.connections.{$connection}.{$configKey}", $configValue);
                        }
                    }
                } else {
                    Config::set("database.{$key}", $value);
                }
            }
        }

        // Apply cache configuration
        if (isset($envConfig['cache'])) {
            foreach ($envConfig['cache'] as $key => $value) {
                Config::set("cache.{$key}", $value);
            }
        }

        // Apply session configuration
        if (isset($envConfig['session'])) {
            foreach ($envConfig['session'] as $key => $value) {
                Config::set("session.{$key}", $value);
            }
        }

        // Apply monitoring configuration
        if (isset($envConfig['monitoring'])) {
            foreach ($envConfig['monitoring'] as $service => $serviceConfig) {
                foreach ($serviceConfig as $key => $value) {
                    Config::set("deployment.monitoring.{$service}.{$key}", $value);
                }
            }
        }

        // Apply security headers for production
        if ($environment === 'production' && isset($envConfig['security'])) {
            $this->applySecurityHeaders($envConfig['security']);
        }
    }

    /**
     * Apply security headers for production environment
     */
    protected function applySecurityHeaders(array $securityConfig): void
    {
        if ($securityConfig['force_https'] ?? false) {
            $this->app['request']->server->set('HTTPS', 'on');
        }

        // Set HSTS header
        if (isset($securityConfig['hsts_max_age']) && $securityConfig['hsts_max_age'] > 0) {
            header("Strict-Transport-Security: max-age={$securityConfig['hsts_max_age']}; includeSubDomains");
        }

        // Set CSP header
        if ($securityConfig['content_security_policy'] ?? false) {
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';");
        }
    }
}