<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class HealthCheckService
{
    /**
     * Perform basic health check for an application
     */
    public function performBasicHealthCheck(string $appName): bool
    {
        try {
            Log::info("Performing health check for app: {$appName}");
            
            // Get app URL
            $appUrl = $this->getAppUrl($appName);
            
            if (!$appUrl) {
                Log::error("Could not determine URL for app: {$appName}");
                return false;
            }
            
            // Perform HTTP health check
            $httpCheck = $this->performHttpHealthCheck($appUrl);
            
            // Perform database connectivity check
            $dbCheck = $this->performDatabaseCheck();
            
            // Perform service integration checks
            $servicesCheck = $this->performServiceIntegrationChecks();
            
            $overallHealth = $httpCheck && $dbCheck && $servicesCheck;
            
            Log::info("Health check completed for {$appName}", [
                'app' => $appName,
                'http_check' => $httpCheck,
                'database_check' => $dbCheck,
                'services_check' => $servicesCheck,
                'overall_health' => $overallHealth
            ]);
            
            return $overallHealth;
            
        } catch (Exception $e) {
            Log::error("Health check failed for {$appName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Perform HTTP health check
     */
    private function performHttpHealthCheck(string $url): bool
    {
        try {
            $response = Http::timeout(30)->get($url);
            
            if ($response->successful()) {
                Log::debug("HTTP health check passed", ['url' => $url, 'status' => $response->status()]);
                return true;
            }
            
            Log::warning("HTTP health check failed", [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return false;
            
        } catch (Exception $e) {
            Log::error("HTTP health check exception", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Perform database connectivity check
     */
    private function performDatabaseCheck(): bool
    {
        try {
            // Simple database connectivity test
            DB::select('SELECT 1');
            
            Log::debug("Database health check passed");
            return true;
            
        } catch (Exception $e) {
            Log::error("Database health check failed", [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Perform service integration checks (Sentry, Flagsmith, Grafana)
     */
    private function performServiceIntegrationChecks(): bool
    {
        $checks = [];
        
        // Sentry check
        $checks['sentry'] = $this->checkSentryIntegration();
        
        // Flagsmith check
        $checks['flagsmith'] = $this->checkFlagsmithIntegration();
        
        // Grafana check
        $checks['grafana'] = $this->checkGrafanaIntegration();
        
        // At least 2 out of 3 services should be healthy for overall health
        $healthyServices = array_filter($checks);
        $isHealthy = count($healthyServices) >= 2;
        
        Log::debug("Service integration checks completed", [
            'checks' => $checks,
            'healthy_count' => count($healthyServices),
            'overall_healthy' => $isHealthy
        ]);
        
        return $isHealthy;
    }

    /**
     * Check Sentry integration
     */
    private function checkSentryIntegration(): bool
    {
        try {
            if (!config('sentry.dsn')) {
                return true; // Not configured, consider healthy
            }
            
            // Test Sentry by capturing a test message
            if (app()->bound('sentry')) {
                app('sentry')->captureMessage('Health check test', 'info');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Log::debug("Sentry health check failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check Flagsmith integration
     */
    private function checkFlagsmithIntegration(): bool
    {
        try {
            $flagsmithUrl = config('flagsmith.api_url');
            
            if (!$flagsmithUrl) {
                return true; // Not configured, consider healthy
            }
            
            // Simple connectivity test to Flagsmith API
            $response = Http::timeout(10)->get($flagsmithUrl . '/health/');
            
            return $response->successful();
            
        } catch (Exception $e) {
            Log::debug("Flagsmith health check failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Check Grafana integration
     */
    private function checkGrafanaIntegration(): bool
    {
        try {
            $grafanaConfig = config('grafana');
            
            if (!$grafanaConfig || !isset($grafanaConfig['api_key'])) {
                return true; // Not configured, consider healthy
            }
            
            // For now, just return true as Grafana is typically write-only
            // In a real implementation, you might check if metrics are being sent
            return true;
            
        } catch (Exception $e) {
            Log::debug("Grafana health check failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get application URL based on app name
     */
    private function getAppUrl(string $appName): ?string
    {
        try {
            $baseUrl = config('deployment.base_url', 'susankshakya.com.np');
            
            // Extract subdomain from app name (e.g., restant-main -> main)
            $subdomain = str_replace('restant-', '', $appName);
            
            return "https://restant.{$subdomain}.{$baseUrl}";
            
        } catch (Exception $e) {
            Log::error("Failed to determine app URL", [
                'app' => $appName,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Perform comprehensive health check with detailed results
     */
    public function performDetailedHealthCheck(string $appName): array
    {
        $results = [
            'app_name' => $appName,
            'timestamp' => now()->toISOString(),
            'overall_health' => false,
            'checks' => []
        ];
        
        try {
            $appUrl = $this->getAppUrl($appName);
            
            // HTTP Check
            $results['checks']['http'] = [
                'name' => 'HTTP Connectivity',
                'status' => $this->performHttpHealthCheck($appUrl),
                'url' => $appUrl
            ];
            
            // Database Check
            $results['checks']['database'] = [
                'name' => 'Database Connectivity',
                'status' => $this->performDatabaseCheck()
            ];
            
            // Service Checks
            $results['checks']['sentry'] = [
                'name' => 'Sentry Integration',
                'status' => $this->checkSentryIntegration()
            ];
            
            $results['checks']['flagsmith'] = [
                'name' => 'Flagsmith Integration',
                'status' => $this->checkFlagsmithIntegration()
            ];
            
            $results['checks']['grafana'] = [
                'name' => 'Grafana Integration',
                'status' => $this->checkGrafanaIntegration()
            ];
            
            // Calculate overall health
            $passedChecks = array_filter($results['checks'], fn($check) => $check['status']);
            $results['overall_health'] = count($passedChecks) >= 4; // At least 4 out of 5 checks should pass
            
            $results['summary'] = [
                'total_checks' => count($results['checks']),
                'passed_checks' => count($passedChecks),
                'failed_checks' => count($results['checks']) - count($passedChecks)
            ];
            
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
            Log::error("Detailed health check failed", [
                'app' => $appName,
                'error' => $e->getMessage()
            ]);
        }
        
        return $results;
    }
}