<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool sendMetric(string $metricName, float $value, array $labels = [])
 * @method static bool sendPerformanceMetrics(array $metrics)
 * @method static void trackHttpRequest(string $method, string $route, int $statusCode, float $duration)
 * @method static void trackDatabaseQuery(string $connection, float $duration, string $query = null)
 * @method static bool sendLogs(array $logs)
 * @method static void trackInfrastructureMetrics()
 * @method static bool healthCheck()
 *
 * @see \App\Services\GrafanaCloudService
 */
class GrafanaCloud extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\GrafanaCloudService::class;
    }
}