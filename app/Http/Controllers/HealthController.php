<?php

namespace App\Http\Controllers;

use App\Services\FlagsmithService;
use App\Services\SentryService;
use App\Services\GrafanaCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function __construct(
        private FlagsmithService $flagsmithService,
        private SentryService $sentryService,
        private GrafanaCloudService $grafanaCloudService
    ) {}

    /**
     * Check overall application health for deployment verification
     */
    public function index(): JsonResponse
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'deployment_environment' => config('app.env'),
            'version' => config('app.version', 'unknown'),
            'services' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'sentry' => $this->checkSentry(),
                'flagsmith' => $this->checkFlagsmith(),
                'grafana' => $this->checkGrafana(),
            ],
            'ssl' => $this->checkSSLCertificate(),
        ];

        // Determine overall status
        $serviceStatuses = collect($health['services'])->values();
        $sslStatus = $health['ssl']['status'] ?? 'unknown';
        
        $criticalServices = ['database', 'cache'];
        $criticalHealthy = collect($criticalServices)->every(fn($service) => 
            ($health['services'][$service] ?? 'unhealthy') === 'healthy'
        );

        if (!$criticalHealthy) {
            $health['status'] = 'unhealthy';
            $httpStatus = 503;
        } elseif ($serviceStatuses->contains('unhealthy') || $sslStatus === 'invalid') {
            $health['status'] = 'degraded';
            $httpStatus = 200; // Still operational but with issues
        } else {
            $health['status'] = 'healthy';
            $httpStatus = 200;
        }

        return response()->json($health, $httpStatus);
    }

    /**
     * Detailed database connectivity verification
     */
    public function database(): JsonResponse
    {
        $checks = [];
        
        try {
            // Test default connection
            $start = microtime(true);
            DB::connection()->getPdo();
            $connectionTime = (microtime(true) - $start) * 1000;
            
            $checks['connection'] = [
                'status' => 'healthy',
                'response_time_ms' => round($connectionTime, 2)
            ];

            // Test basic query
            $start = microtime(true);
            $result = DB::select('SELECT 1 as test');
            $queryTime = (microtime(true) - $start) * 1000;
            
            $checks['query'] = [
                'status' => $result[0]->test === 1 ? 'healthy' : 'unhealthy',
                'response_time_ms' => round($queryTime, 2)
            ];

            // Test migrations table (deployment verification)
            try {
                $migrationCount = DB::table('migrations')->count();
                $checks['migrations'] = [
                    'status' => 'healthy',
                    'count' => $migrationCount
                ];
            } catch (\Exception $e) {
                $checks['migrations'] = [
                    'status' => 'unhealthy',
                    'error' => 'Migrations table not accessible'
                ];
            }

        } catch (\Exception $e) {
            $checks['connection'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        $overallHealthy = collect($checks)->every(fn($check) => $check['status'] === 'healthy');

        return response()->json([
            'service' => 'database',
            'status' => $overallHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks
        ], $overallHealthy ? 200 : 503);
    }

    /**
     * Check Sentry service integration
     */
    public function sentry(): JsonResponse
    {
        $checks = [];
        
        try {
            // Test if Sentry is configured
            $checks['configuration'] = [
                'status' => config('sentry.dsn') ? 'healthy' : 'unhealthy',
                'dsn_configured' => !empty(config('sentry.dsn'))
            ];

            // Test Sentry service integration
            if (config('sentry.dsn')) {
                $testResult = $this->sentryService->testIntegration();
                $checks['integration'] = [
                    'status' => $testResult['overall_success'] ? 'healthy' : 'unhealthy',
                    'message_capture' => $testResult['message_capture']['success'] ?? false,
                    'exception_capture' => $testResult['exception_capture']['success'] ?? false
                ];
            }

        } catch (\Exception $e) {
            $checks['integration'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        $overallHealthy = collect($checks)->every(fn($check) => $check['status'] === 'healthy');

        return response()->json([
            'service' => 'sentry',
            'status' => $overallHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks
        ], $overallHealthy ? 200 : 503);
    }

    /**
     * Check Flagsmith service health
     */
    public function flagsmith(): JsonResponse
    {
        $checks = [];
        
        try {
            // Test configuration
            $checks['configuration'] = [
                'status' => config('flagsmith.environment_key') ? 'healthy' : 'unhealthy',
                'api_url' => config('flagsmith.api_url'),
                'enabled' => config('flagsmith.enabled', false)
            ];

            // Test connectivity
            $start = microtime(true);
            $isHealthy = $this->flagsmithService->healthCheck();
            $responseTime = (microtime(true) - $start) * 1000;
            
            $checks['connectivity'] = [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'response_time_ms' => round($responseTime, 2)
            ];

            // Test flag retrieval
            try {
                $testFlag = $this->flagsmithService->getFlag('health_check_test', false);
                $checks['flag_retrieval'] = [
                    'status' => 'healthy',
                    'test_flag_value' => $testFlag
                ];
            } catch (\Exception $e) {
                $checks['flag_retrieval'] = [
                    'status' => 'degraded',
                    'error' => 'Using fallback values'
                ];
            }

        } catch (\Exception $e) {
            $checks['connectivity'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        $overallHealthy = collect($checks)->every(fn($check) => 
            in_array($check['status'], ['healthy', 'degraded'])
        );

        return response()->json([
            'service' => 'flagsmith',
            'status' => $overallHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks
        ], $overallHealthy ? 200 : 503);
    }

    /**
     * Check Grafana Cloud service integration
     */
    public function grafana(): JsonResponse
    {
        $checks = [];
        
        try {
            // Test configuration
            $checks['configuration'] = [
                'status' => (config('monitoring.grafana.api_key') && config('monitoring.grafana.instance_id')) ? 'healthy' : 'unhealthy',
                'enabled' => config('monitoring.grafana.enabled', false)
            ];

            // Test connectivity
            if (config('monitoring.grafana.enabled')) {
                $start = microtime(true);
                $isHealthy = $this->grafanaCloudService->healthCheck();
                $responseTime = (microtime(true) - $start) * 1000;
                
                $checks['connectivity'] = [
                    'status' => $isHealthy ? 'healthy' : 'unhealthy',
                    'response_time_ms' => round($responseTime, 2)
                ];
            } else {
                $checks['connectivity'] = [
                    'status' => 'disabled',
                    'message' => 'Grafana Cloud monitoring is disabled'
                ];
            }

        } catch (\Exception $e) {
            $checks['connectivity'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage()
            ];
        }

        $overallHealthy = collect($checks)->every(fn($check) => 
            in_array($check['status'], ['healthy', 'disabled'])
        );

        return response()->json([
            'service' => 'grafana',
            'status' => $overallHealthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'checks' => $checks
        ], $overallHealthy ? 200 : 503);
    }

    /**
     * Check SSL certificate validation
     */
    public function ssl(): JsonResponse
    {
        $sslInfo = $this->checkSSLCertificate();
        
        return response()->json([
            'service' => 'ssl',
            'status' => $sslInfo['status'],
            'timestamp' => now()->toISOString(),
            'certificate' => $sslInfo
        ], $sslInfo['status'] === 'valid' ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();
            // Additional check: verify we can run a simple query
            DB::select('SELECT 1');
            return 'healthy';
        } catch (\Exception $e) {
            Log::error('Database health check failed', ['error' => $e->getMessage()]);
            return 'unhealthy';
        }
    }

    private function checkCache(): string
    {
        try {
            $testKey = 'health_check_' . time();
            $testValue = 'test_' . uniqid();
            
            Cache::put($testKey, $testValue, 10);
            $retrievedValue = Cache::get($testKey);
            Cache::forget($testKey);
            
            return $retrievedValue === $testValue ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            Log::error('Cache health check failed', ['error' => $e->getMessage()]);
            return 'unhealthy';
        }
    }

    private function checkSentry(): string
    {
        try {
            if (!config('sentry.dsn')) {
                return 'disabled';
            }

            // Test basic Sentry functionality
            $testResult = $this->sentryService->testIntegration();
            return $testResult['overall_success'] ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            Log::error('Sentry health check failed', ['error' => $e->getMessage()]);
            return 'unhealthy';
        }
    }

    private function checkFlagsmith(): string
    {
        try {
            return $this->flagsmithService->healthCheck() ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            Log::error('Flagsmith health check failed', ['error' => $e->getMessage()]);
            return 'unhealthy';
        }
    }

    private function checkGrafana(): string
    {
        try {
            if (!config('monitoring.grafana.enabled')) {
                return 'disabled';
            }

            return $this->grafanaCloudService->healthCheck() ? 'healthy' : 'unhealthy';
        } catch (\Exception $e) {
            Log::error('Grafana health check failed', ['error' => $e->getMessage()]);
            return 'unhealthy';
        }
    }

    private function checkSSLCertificate(): array
    {
        try {
            $domain = request()->getHost();
            
            // Skip SSL check for localhost or IP addresses
            if (in_array($domain, ['localhost', '127.0.0.1']) || filter_var($domain, FILTER_VALIDATE_IP)) {
                return [
                    'status' => 'not_applicable',
                    'domain' => $domain,
                    'message' => 'SSL check not applicable for localhost/IP'
                ];
            }

            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ]);

            $socket = @stream_socket_client(
                "ssl://{$domain}:443",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                return [
                    'status' => 'invalid',
                    'domain' => $domain,
                    'error' => "Cannot connect to SSL: {$errstr} ({$errno})"
                ];
            }

            $cert = stream_context_get_params($socket)['options']['ssl']['peer_certificate'];
            fclose($socket);

            if (!$cert) {
                return [
                    'status' => 'invalid',
                    'domain' => $domain,
                    'error' => 'No certificate found'
                ];
            }

            $certInfo = openssl_x509_parse($cert);
            $validFrom = $certInfo['validFrom_time_t'];
            $validTo = $certInfo['validTo_time_t'];
            $now = time();

            $daysUntilExpiry = ($validTo - $now) / (24 * 60 * 60);

            $status = 'valid';
            if ($now < $validFrom) {
                $status = 'not_yet_valid';
            } elseif ($now > $validTo) {
                $status = 'expired';
            } elseif ($daysUntilExpiry < 30) {
                $status = 'expiring_soon';
            }

            return [
                'status' => $status,
                'domain' => $domain,
                'issuer' => $certInfo['issuer']['CN'] ?? 'Unknown',
                'subject' => $certInfo['subject']['CN'] ?? 'Unknown',
                'valid_from' => date('Y-m-d H:i:s', $validFrom),
                'valid_to' => date('Y-m-d H:i:s', $validTo),
                'days_until_expiry' => round($daysUntilExpiry, 1),
                'serial_number' => $certInfo['serialNumber'] ?? null,
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'domain' => request()->getHost(),
                'error' => $e->getMessage()
            ];
        }
    }
}