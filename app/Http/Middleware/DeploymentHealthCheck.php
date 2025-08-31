<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeploymentHealthCheck
{
    /**
     * Handle an incoming request during deployment verification.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip health checks for health endpoints themselves
        if ($request->is('health*')) {
            return $next($request);
        }

        // Skip health checks in local development
        if (config('app.env') === 'local') {
            return $next($request);
        }

        // Perform critical health checks
        $healthStatus = $this->performCriticalHealthChecks();

        if (!$healthStatus['healthy']) {
            Log::warning('Application health check failed during request', [
                'url' => $request->fullUrl(),
                'health_status' => $healthStatus,
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'error' => 'Service temporarily unavailable',
                'message' => 'The application is currently undergoing maintenance or experiencing issues.',
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
            ], 503);
        }

        return $next($request);
    }

    /**
     * Perform critical health checks that must pass for the application to serve requests.
     */
    private function performCriticalHealthChecks(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        $healthy = collect($checks)->every(fn($status) => $status === true);

        return [
            'healthy' => $healthy,
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Check database connectivity.
     */
    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::error('Critical database health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Check cache functionality.
     */
    private function checkCache(): bool
    {
        try {
            $testKey = 'deployment_health_check_' . time();
            $testValue = 'test_' . uniqid();
            
            Cache::put($testKey, $testValue, 10);
            $retrievedValue = Cache::get($testKey);
            Cache::forget($testKey);
            
            return $retrievedValue === $testValue;
        } catch (\Exception $e) {
            Log::error('Critical cache health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
}