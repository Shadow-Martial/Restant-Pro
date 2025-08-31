<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class EnvironmentManager
{
    /**
     * Supported environments
     */
    const ENVIRONMENTS = ['production', 'staging', 'testing', 'local'];

    /**
     * Sensitive configuration keys that should be encrypted
     */
    const SENSITIVE_KEYS = [
        'DB_PASSWORD',
        'REDIS_PASSWORD',
        'MAIL_PASSWORD',
        'SENTRY_LARAVEL_DSN',
        'FLAGSMITH_ENVIRONMENT_KEY',
        'GRAFANA_CLOUD_API_KEY',
        'APP_KEY',
        'JWT_SECRET',
        'PUSHER_APP_SECRET',
        'AWS_SECRET_ACCESS_KEY',
        'STRIPE_SECRET',
        'PAYPAL_SECRET',
    ];

    /**
     * Required configuration keys per environment
     */
    const REQUIRED_KEYS = [
        'production' => [
            'APP_KEY',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
            'SENTRY_LARAVEL_DSN',
            'FLAGSMITH_ENVIRONMENT_KEY',
            'GRAFANA_CLOUD_API_KEY',
        ],
        'staging' => [
            'APP_KEY',
            'DB_DATABASE',
            'DB_USERNAME',
            'SENTRY_LARAVEL_DSN',
            'FLAGSMITH_ENVIRONMENT_KEY',
        ],
        'testing' => [
            'APP_KEY',
        ],
        'local' => [
            'APP_KEY',
        ],
    ];

    /**
     * Get current environment
     */
    public function getCurrentEnvironment(): string
    {
        return config('app.env', 'production');
    }

    /**
     * Validate if environment is supported
     */
    public function isValidEnvironment(string $environment): bool
    {
        return in_array($environment, self::ENVIRONMENTS);
    }

    /**
     * Get environment-specific configuration
     */
    public function getEnvironmentConfig(string $environment): array
    {
        if (!$this->isValidEnvironment($environment)) {
            throw new InvalidArgumentException("Invalid environment: {$environment}");
        }

        return config("deployment.environments.{$environment}", []);
    }

    /**
     * Check if a configuration key is sensitive
     */
    public function isSensitiveKey(string $key): bool
    {
        return in_array($key, self::SENSITIVE_KEYS);
    }

    /**
     * Mask sensitive values for logging
     */
    public function maskSensitiveValue(string $key, $value): string
    {
        if ($this->isSensitiveKey($key)) {
            if (empty($value)) {
                return '[EMPTY]';
            }
            return '[MASKED:' . substr(md5($value), 0, 8) . ']';
        }

        return $value;
    }

    /**
     * Get required configuration keys for environment
     */
    public function getRequiredKeys(string $environment): array
    {
        return self::REQUIRED_KEYS[$environment] ?? [];
    }

    /**
     * Validate environment configuration
     */
    public function validateEnvironmentConfig(string $environment): array
    {
        $errors = [];
        $requiredKeys = $this->getRequiredKeys($environment);

        foreach ($requiredKeys as $key) {
            $value = env($key);
            
            if (empty($value)) {
                $errors[] = "Missing required configuration: {$key}";
            }
        }

        // Environment-specific validations
        switch ($environment) {
            case 'production':
                if (env('APP_DEBUG', false)) {
                    $errors[] = 'APP_DEBUG should be false in production';
                }
                if (env('APP_ENV') !== 'production') {
                    $errors[] = 'APP_ENV should be "production" in production environment';
                }
                break;

            case 'staging':
                if (env('APP_ENV') !== 'staging') {
                    $errors[] = 'APP_ENV should be "staging" in staging environment';
                }
                break;
        }

        return $errors;
    }

    /**
     * Get safe configuration for logging (with sensitive values masked)
     */
    public function getSafeConfig(): array
    {
        $config = [];
        $envVars = $_ENV;

        foreach ($envVars as $key => $value) {
            $config[$key] = $this->maskSensitiveValue($key, $value);
        }

        return $config;
    }

    /**
     * Log environment configuration status
     */
    public function logEnvironmentStatus(): void
    {
        $environment = $this->getCurrentEnvironment();
        $errors = $this->validateEnvironmentConfig($environment);

        if (empty($errors)) {
            Log::info('Environment configuration validated successfully', [
                'environment' => $environment,
                'config' => $this->getSafeConfig(),
            ]);
        } else {
            Log::error('Environment configuration validation failed', [
                'environment' => $environment,
                'errors' => $errors,
            ]);
        }
    }
}