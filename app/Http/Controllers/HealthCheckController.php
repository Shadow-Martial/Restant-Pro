<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\GrafanaCloudService;
use App\Services\SentryService;
use App\Services\FlagsmithService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthCheckController extends Controller
{
    private GrafanaCloudService $grafanaService;
    private SentryService $sentryService;
    private FlagsmithService $flagsmithService;

    public function __construct(
        GrafanaCloudService $grafanaService,
        SentryService $sentryService,
        FlagsmithService $flagsmithService
    ) {
        $this->grafanaService = $grafanaService;
        $this->sentryService = $sentryService;
        $this->flagsmithService = $flagsmithService;
    }

    /**
     * Comprehensive health check including all monitoring services
     */
    public function check(Request $request): JsonResponse
    {
        $checks = [];
        $overallStatus = 'healthy';

        // Basic application health
        $checks['app'] = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ];

        // Database health
        $checks['database'] = $this->checkDatabase();
        if ($checks['database']['status'] !== 'healthy') {
            $overallStatus = 'degraded';
        }

        // Cache health
        $checks['cache'] = $this->checkCache();
        if ($checks['cache']['status'] !== 'healthy') {
            $overallStatus = 'degraded';
        }

        // Monitoring services health
        if (config('monitoring.sentry.enabled', true)) {
            $checks['sentry'] = $this->checkSentry();
            if ($checks['sentry']['status'] !== 'healthy') {
                $overallStatus = 'degraded';
            }
        }

        if (config('monitoring.flagsmith.enabled', true)) {
            $checks['flagsmith'] = $this->checkFlagsmith();
            if ($checks['flagsmith']['status'] !== 'healthy') {
                $overallStatus = 'degraded';
            }
        }

        if (config('monitoring.grafana.enabled', true)) {
            $checks['grafana'] = $this->checkGrafana();
            if ($checks['grafana']['status'] !== 'healthy') {
                $overallStatus = 'degraded';
            }
        }

        $response = [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'checks' => $checks,
        ];

        // Send health check metrics to Grafana
        if (config('monitoring.grafana.enabled', true)) {
            $this->sendHealthMetrics($checks);
        }

        $statusCode = $overallStatus === 'healthy' ? 200 : 503;
        
        return response()->json($response, $statusCode);
    }

    /**
     * Simple health check endpoint for load balancers
     */
    public function simple(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'response_time_ms' => $this->measureResponseTime(function () {
                    DB::select('SELECT 1');
                }),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connectivity
     */
    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test';
            
            $responseTime = $this->measureResponseTime(function () use ($testKey, $testValue) {
                Cache::put($testKey, $testValue, 10);
                $retrieved = Cache::get($testKey);
                Cache::forget($testKey);
                
                if ($retrieved !== $testValue) {
                    throw new \Exception('Cache value mismatch');
                }
            });

            return [
                'status' => 'healthy',
                'message' => 'Cache operations successful',
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache operations failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Sentry connectivity
     */
    private function checkSentry(): array
    {
        try {
            // This is a basic check - in production you might want to send a test event
            $enabled = config('sentry.dsn') && config('monitoring.sentry.enabled', true);
            
            return [
                'status' => $enabled ? 'healthy' : 'disabled',
                'message' => $enabled ? 'Sentry configuration valid' : 'Sentry disabled',
                'configured' => $enabled,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Sentry check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Flagsmith connectivity
     */
    private function checkFlagsmith(): array
    {
        try {
            $responseTime = $this->measureResponseTime(function () {
                // Try to get a test flag or just check if service is reachable
                $this->flagsmithService->isEnabled('health_check_flag');
            });

            return [
                'status' => 'healthy',
                'message' => 'Flagsmith service reachable',
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Flagsmith service unreachable',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Grafana Cloud connectivity
     */
    private function checkGrafana(): array
    {
        try {
            $responseTime = $this->measureResponseTime(function () {
                $this->grafanaService->healthCheck();
            });

            return [
                'status' => 'healthy',
                'message' => 'Grafana Cloud service reachable',
                'response_time_ms' => $responseTime,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Grafana Cloud service unreachable',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send health check metrics to Grafana
     */
    private function sendHealthMetrics(array $checks): void
    {
        $metrics = [];

        foreach ($checks as $service => $check) {
            $status = $check['status'] === 'healthy' ? 1 : 0;
            
            $metrics[] = [
                'name' => 'laravel_service_health',
                'value' => $status,
                'labels' => [
                    'service' => $service,
                    'status' => $check['status'],
                ]
            ];

            // Add response time metrics if available
            if (isset($check['response_time_ms'])) {
                $metrics[] = [
                    'name' => 'laravel_service_response_time_ms',
                    'value' => $check['response_time_ms'],
                    'labels' => [
                        'service' => $service,
                    ]
                ];
            }
        }

        $this->grafanaService->sendPerformanceMetrics($metrics);
    }

    /**
     * Measure response time for a callback
     */
    private function measureResponseTime(callable $callback): float
    {
        $start = microtime(true);
        $callback();
        $end = microtime(true);
        
        return round(($end - $start) * 1000, 2); // Convert to milliseconds
    }
}