<?php

namespace App\Services;

use Flagsmith\Flagsmith;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class FlagsmithService
{
    private $flagsmith;
    private $cachePrefix = 'flagsmith_';
    private $cacheTtl = 300; // 5 minutes
    private $circuitBreakerKey = 'flagsmith_circuit_breaker';
    private $circuitBreakerThreshold = 5; // failures before opening circuit
    private $circuitBreakerTimeout = 300; // 5 minutes before trying again

    public function __construct()
    {
        $this->flagsmith = new Flagsmith(config('flagsmith.environment_key'));
        
        if (config('flagsmith.api_url')) {
            $this->flagsmith->withApiUrl(config('flagsmith.api_url'));
        }
    }

    /**
     * Get a feature flag value with fallback
     */
    public function getFlag(string $flagName, $defaultValue = false, ?string $identity = null)
    {
        // If Flagsmith is disabled, use config defaults
        if (!config('flagsmith.enabled')) {
            return config("flagsmith.default_flags.{$flagName}", $defaultValue);
        }

        $cacheKey = $this->getCacheKey($flagName, $identity);
        
        try {
            // Try to get from cache first
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // Check circuit breaker
            if ($this->isCircuitBreakerOpen()) {
                throw new Exception('Circuit breaker is open');
            }

            // Get from Flagsmith API
            if ($identity) {
                $flags = $this->flagsmith->getIdentityFlags($identity);
            } else {
                $flags = $this->flagsmith->getEnvironmentFlags();
            }

            $flag = $flags->getFlag($flagName);
            $value = $flag ? $flag->getValue() : $defaultValue;

            // Reset circuit breaker on success
            $this->resetCircuitBreaker();

            // Cache the result with extended TTL for fallback
            Cache::put($cacheKey, $value, $this->cacheTtl);
            Cache::put($cacheKey . '_fallback', $value, $this->cacheTtl * 12); // 1 hour fallback cache

            return $value;
        } catch (Exception $e) {
            // Increment circuit breaker failure count
            $this->incrementCircuitBreakerFailures();
            
            if (config('flagsmith.fallback.log_failures')) {
                Log::warning('Flagsmith service unavailable', [
                    'flag' => $flagName,
                    'identity' => $identity,
                    'error' => $e->getMessage()
                ]);
            }

            // Try fallback cache first (longer TTL)
            if (Cache::has($cacheKey . '_fallback')) {
                return Cache::get($cacheKey . '_fallback');
            }

            // Try regular cache
            if (Cache::has($cacheKey)) {
                return Cache::get($cacheKey);
            }

            // Use config default
            $configDefault = config("flagsmith.default_flags.{$flagName}");
            if ($configDefault !== null) {
                return $configDefault;
            }

            // Final fallback to provided default
            return $defaultValue;
        }
    }

    /**
     * Check if a feature is enabled
     */
    public function isEnabled(string $flagName, ?string $identity = null): bool
    {
        return (bool) $this->getFlag($flagName, false, $identity);
    }

    /**
     * Get multiple flags at once
     */
    public function getFlags(array $flagNames, ?string $identity = null): array
    {
        $results = [];
        
        foreach ($flagNames as $flagName => $defaultValue) {
            if (is_numeric($flagName)) {
                $flagName = $defaultValue;
                $defaultValue = false;
            }
            
            $results[$flagName] = $this->getFlag($flagName, $defaultValue, $identity);
        }

        return $results;
    }

    /**
     * Clear cache for a specific flag
     */
    public function clearCache(string $flagName, ?string $identity = null): void
    {
        $cacheKey = $this->getCacheKey($flagName, $identity);
        Cache::forget($cacheKey);
        Cache::forget($cacheKey . '_fallback');
    }

    /**
     * Clear all Flagsmith cache
     */
    public function clearAllCache(): void
    {
        // More targeted cache clearing
        $keys = Cache::getRedis()->keys($this->cachePrefix . '*');
        foreach ($keys as $key) {
            Cache::forget(str_replace(config('cache.prefix') . ':', '', $key));
        }
    }

    /**
     * Get cache key for flag
     */
    private function getCacheKey(string $flagName, ?string $identity = null): string
    {
        return $this->cachePrefix . $flagName . ($identity ? '_' . $identity : '');
    }

    /**
     * Health check for Flagsmith service
     */
    public function healthCheck(): bool
    {
        try {
            $this->flagsmith->getEnvironmentFlags();
            $this->resetCircuitBreaker();
            return true;
        } catch (Exception $e) {
            Log::error('Flagsmith health check failed', ['error' => $e->getMessage()]);
            $this->incrementCircuitBreakerFailures();
            return false;
        }
    }

    /**
     * Check if circuit breaker is open
     */
    private function isCircuitBreakerOpen(): bool
    {
        $failures = Cache::get($this->circuitBreakerKey . '_failures', 0);
        $lastFailure = Cache::get($this->circuitBreakerKey . '_last_failure');

        if ($failures >= $this->circuitBreakerThreshold) {
            if ($lastFailure && (time() - $lastFailure) < $this->circuitBreakerTimeout) {
                return true;
            }
        }

        return false;
    }

    /**
     * Increment circuit breaker failure count
     */
    private function incrementCircuitBreakerFailures(): void
    {
        $failures = Cache::get($this->circuitBreakerKey . '_failures', 0) + 1;
        Cache::put($this->circuitBreakerKey . '_failures', $failures, 3600); // 1 hour
        Cache::put($this->circuitBreakerKey . '_last_failure', time(), 3600);
    }

    /**
     * Reset circuit breaker
     */
    private function resetCircuitBreaker(): void
    {
        Cache::forget($this->circuitBreakerKey . '_failures');
        Cache::forget($this->circuitBreakerKey . '_last_failure');
    }
}