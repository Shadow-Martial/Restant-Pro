<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GrafanaCloudService
{
    private string $apiKey;
    private string $instanceId;
    private string $metricsUrl;
    private string $logsUrl;
    private bool $enabled;

    public function __construct()
    {
        $this->apiKey = config('monitoring.grafana.api_key');
        $this->instanceId = config('monitoring.grafana.instance_id');
        $this->enabled = config('monitoring.grafana.enabled', false);
        
        $this->metricsUrl = "https://prometheus-prod-01-eu-west-0.grafana.net/api/prom/push";
        $this->logsUrl = "https://logs-prod-eu-west-0.grafana.net/loki/api/v1/push";
    }

    /**
     * Send custom metrics to Grafana Cloud
     */
    public function sendMetric(string $metricName, float $value, array $labels = []): bool
    {
        if (!$this->enabled || !$this->apiKey) {
            return false;
        }

        try {
            $timestamp = now()->timestamp * 1000; // Convert to milliseconds
            
            $labelString = $this->formatLabels($labels);
            $metricData = [
                'streams' => [
                    [
                        'stream' => array_merge(['__name__' => $metricName], $labels),
                        'values' => [
                            [(string)$timestamp, (string)$value]
                        ]
                    ]
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->instanceId . ':' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->metricsUrl, $metricData);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Failed to send metric to Grafana Cloud', [
                'metric' => $metricName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send application performance metrics
     */
    public function sendPerformanceMetrics(array $metrics): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $success = true;
        foreach ($metrics as $metric) {
            $result = $this->sendMetric(
                $metric['name'],
                $metric['value'],
                array_merge($metric['labels'] ?? [], [
                    'app' => config('app.name'),
                    'environment' => config('app.env')
                ])
            );
            $success = $success && $result;
        }

        return $success;
    }

    /**
     * Track HTTP request metrics
     */
    public function trackHttpRequest(string $method, string $route, int $statusCode, float $duration): void
    {
        $this->sendMetric('laravel_http_requests_total', 1, [
            'method' => $method,
            'route' => $route,
            'status_code' => (string)$statusCode,
            'app' => config('app.name'),
            'environment' => config('app.env')
        ]);

        $this->sendMetric('laravel_http_request_duration_seconds', $duration, [
            'method' => $method,
            'route' => $route,
            'app' => config('app.name'),
            'environment' => config('app.env')
        ]);
    }

    /**
     * Track database query metrics
     */
    public function trackDatabaseQuery(string $connection, float $duration, string $query = null): void
    {
        $this->sendMetric('laravel_database_queries_total', 1, [
            'connection' => $connection,
            'app' => config('app.name'),
            'environment' => config('app.env')
        ]);

        $this->sendMetric('laravel_database_query_duration_seconds', $duration, [
            'connection' => $connection,
            'app' => config('app.name'),
            'environment' => config('app.env')
        ]);
    }

    /**
     * Send logs to Grafana Cloud Loki
     */
    public function sendLogs(array $logs): bool
    {
        if (!$this->enabled || !$this->apiKey) {
            return false;
        }

        try {
            $streams = [];
            foreach ($logs as $log) {
                $timestamp = isset($log['timestamp']) 
                    ? $log['timestamp'] * 1000000000 // Convert to nanoseconds
                    : now()->timestamp * 1000000000;

                $labels = array_merge([
                    'app' => config('app.name'),
                    'environment' => config('app.env'),
                    'level' => $log['level'] ?? 'info'
                ], $log['labels'] ?? []);

                $streams[] = [
                    'stream' => $labels,
                    'values' => [
                        [(string)$timestamp, $log['message']]
                    ]
                ];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->instanceId . ':' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->logsUrl, ['streams' => $streams]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning('Failed to send logs to Grafana Cloud', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Track infrastructure metrics
     */
    public function trackInfrastructureMetrics(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $this->sendMetric('laravel_memory_usage_bytes', $memoryUsage, [
            'app' => config('app.name'),
            'environment' => config('app.env')
        ]);

        // Peak memory usage
        $peakMemory = memory_get_peak_usage(true);
        $this->sendMetric('laravel_memory_peak_bytes', $peakMemory, [
            'app' => config('app.name'),
            'environment' => config('app.env')
        ]);

        // Cache metrics if available
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            try {
                $redis = Cache::getRedis();
                $info = $redis->info();
                
                if (isset($info['used_memory'])) {
                    $this->sendMetric('redis_memory_usage_bytes', (float)$info['used_memory'], [
                        'app' => config('app.name'),
                        'environment' => config('app.env')
                    ]);
                }
            } catch (\Exception $e) {
                // Silently fail if Redis info is not available
            }
        }
    }

    /**
     * Format labels for Prometheus format
     */
    private function formatLabels(array $labels): string
    {
        if (empty($labels)) {
            return '';
        }

        $formatted = [];
        foreach ($labels as $key => $value) {
            $formatted[] = $key . '="' . addslashes($value) . '"';
        }

        return '{' . implode(',', $formatted) . '}';
    }

    /**
     * Check if Grafana Cloud integration is healthy
     */
    public function healthCheck(): bool
    {
        if (!$this->enabled || !$this->apiKey) {
            return false;
        }

        try {
            // Send a test metric to verify connectivity
            return $this->sendMetric('laravel_health_check', 1, [
                'app' => config('app.name'),
                'environment' => config('app.env'),
                'check' => 'grafana_connectivity'
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }
}