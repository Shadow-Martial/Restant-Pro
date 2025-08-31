<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\GrafanaCloudService;

class GrafanaPerformanceMiddleware
{
    private GrafanaCloudService $grafanaService;

    public function __construct(GrafanaCloudService $grafanaService)
    {
        $this->grafanaService = $grafanaService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startQueries = $this->getQueryCount();

        $response = $next($request);

        $endTime = microtime(true);
        $endQueries = $this->getQueryCount();

        $this->trackPerformanceMetrics($request, $response, $startTime, $endTime, $startQueries, $endQueries);

        return $response;
    }

    /**
     * Track performance metrics for the request
     */
    private function trackPerformanceMetrics(Request $request, $response, float $startTime, float $endTime, int $startQueries, int $endQueries): void
    {
        $duration = $endTime - $startTime;
        $queryCount = $endQueries - $startQueries;
        
        $route = $request->route() ? $request->route()->getName() ?? $request->route()->uri() : 'unknown';
        $method = $request->method();
        $statusCode = $response->getStatusCode();

        // Track HTTP request metrics
        $this->grafanaService->trackHttpRequest($method, $route, $statusCode, $duration);

        // Track additional performance metrics
        $metrics = [
            [
                'name' => 'laravel_request_queries_total',
                'value' => $queryCount,
                'labels' => [
                    'method' => $method,
                    'route' => $route,
                    'status_code' => (string)$statusCode
                ]
            ],
            [
                'name' => 'laravel_request_memory_usage_bytes',
                'value' => memory_get_usage(true),
                'labels' => [
                    'method' => $method,
                    'route' => $route
                ]
            ]
        ];

        // Track slow requests
        if ($duration > 1.0) { // Requests taking more than 1 second
            $metrics[] = [
                'name' => 'laravel_slow_requests_total',
                'value' => 1,
                'labels' => [
                    'method' => $method,
                    'route' => $route,
                    'duration_bucket' => $this->getDurationBucket($duration)
                ]
            ];
        }

        // Track high query count requests
        if ($queryCount > 10) {
            $metrics[] = [
                'name' => 'laravel_high_query_requests_total',
                'value' => 1,
                'labels' => [
                    'method' => $method,
                    'route' => $route,
                    'query_bucket' => $this->getQueryBucket($queryCount)
                ]
            ];
        }

        // Track error responses
        if ($statusCode >= 400) {
            $metrics[] = [
                'name' => 'laravel_error_responses_total',
                'value' => 1,
                'labels' => [
                    'method' => $method,
                    'route' => $route,
                    'status_code' => (string)$statusCode,
                    'error_type' => $this->getErrorType($statusCode)
                ]
            ];
        }

        $this->grafanaService->sendPerformanceMetrics($metrics);
    }

    /**
     * Get current database query count
     */
    private function getQueryCount(): int
    {
        return collect(DB::getQueryLog())->count();
    }

    /**
     * Get duration bucket for slow request categorization
     */
    private function getDurationBucket(float $duration): string
    {
        if ($duration < 2.0) return '1-2s';
        if ($duration < 5.0) return '2-5s';
        if ($duration < 10.0) return '5-10s';
        return '10s+';
    }

    /**
     * Get query count bucket for high query request categorization
     */
    private function getQueryBucket(int $queryCount): string
    {
        if ($queryCount < 20) return '10-20';
        if ($queryCount < 50) return '20-50';
        if ($queryCount < 100) return '50-100';
        return '100+';
    }

    /**
     * Get error type based on status code
     */
    private function getErrorType(int $statusCode): string
    {
        if ($statusCode >= 400 && $statusCode < 500) {
            return 'client_error';
        }
        if ($statusCode >= 500) {
            return 'server_error';
        }
        return 'unknown';
    }
}