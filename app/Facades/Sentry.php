<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null captureException(\Throwable $exception, array $context = [])
 * @method static string|null captureMessage(string $message, string $level = 'info', array $context = [])
 * @method static string|null captureBusinessError(string $message, array $context = [])
 * @method static string|null capturePerformanceIssue(string $operation, float $duration, array $context = [])
 * @method static void addDeploymentContext(string $version, string $environment)
 * @method static array testIntegration()
 *
 * @see \App\Services\SentryService
 */
class Sentry extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\SentryService::class;
    }
}