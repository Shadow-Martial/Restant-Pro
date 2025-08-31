<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FlagsmithService;
use App\Services\SentryService;
use App\Services\GrafanaCloudService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VerifyDeploymentHealth extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'deployment:health-check 
                            {--timeout=30 : Timeout in seconds for health checks}
                            {--critical-only : Only check critical services}
                            {--format=table : Output format (table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'Verify deployment health by checking all integrated services';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Starting deployment health verification...');
        $this->newLine();

        $timeout = (int) $this->option('timeout');
        $criticalOnly = $this->option('critical-only');
        $format = $this->option('format');

        $results = [];
        $overallHealthy = true;

        // Critical services (must be healthy for deployment to succeed)
        $criticalServices = [
            'Database' => [$this, 'checkDatabase'],
            'Cache' => [$this, 'checkCache'],
        ];

        // Optional services (can be degraded but deployment can still succeed)
        $optionalServices = [
            'Sentry' => [$this, 'checkSentry'],
            'Flagsmith' => [$this, 'checkFlagsmith'],
            'Grafana Cloud' => [$this, 'checkGrafana'],
            'SSL Certificate' => [$this, 'checkSSL'],
        ];

        // Check critical services
        foreach ($criticalServices as $serviceName => $checkMethod) {
            $this->info("Checking {$serviceName}...");
            
            $result = $this->runWithTimeout($checkMethod, $timeout);
            $results[$serviceName] = $result;
            
            if (!$result['healthy']) {
                $overallHealthy = false;
                $this->error("❌ {$serviceName}: {$result['message']}");
            } else {
                $this->info("✅ {$serviceName}: {$result['message']}");
            }
        }

        // Check optional services (unless critical-only flag is set)
        if (!$criticalOnly) {
            foreach ($optionalServices as $serviceName => $checkMethod) {
                $this->info("Checking {$serviceName}...");
                
                $result = $this->runWithTimeout($checkMethod, $timeout);
                $results[$serviceName] = $result;
                
                if (!$result['healthy']) {
                    $this->warn("⚠️  {$serviceName}: {$result['message']}");
                } else {
                    $this->info("✅ {$serviceName}: {$result['message']}");
                }
            }
        }

        $this->newLine();

        // Output results
        if ($format === 'json') {
            $this->line(json_encode([
                'overall_healthy' => $overallHealthy,
                'timestamp' => now()->toISOString(),
                'services' => $results,
            ], JSON_PRETTY_PRINT));
        } else {
            $this->displayResultsTable($results, $overallHealthy);
        }

        if ($overallHealthy) {
            $this->info('🎉 Deployment health verification passed!');
            return Command::SUCCESS;
        } else {
            $this->error('💥 Deployment health verification failed!');
            return Command::FAILURE;
        }
    }

    private function runWithTimeout(callable $method, int $timeout): array
    {
        $start = microtime(true);
        
        try {
            $result = call_user_func($method);
            $duration = (microtime(true) - $start) * 1000;
            
            if ($duration > ($timeout * 1000)) {
                return [
                    'healthy' => false,
                    'message' => "Timeout after {$timeout}s",
                    'duration_ms' => round($duration, 2),
                ];
            }
            
            return array_merge($result, [
                'duration_ms' => round($duration, 2),
            ]);
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }
    }

    private function checkDatabase(): array
    {
        try {
            // Test connection
            DB::connection()->getPdo();
            
            // Test query
            $result = DB::select('SELECT 1 as test');
            if ($result[0]->test !== 1) {
                throw new \Exception('Database query returned unexpected result');
            }
            
            // Check migrations
            $migrationCount = DB::table('migrations')->count();
            
            return [
                'healthy' => true,
                'message' => "Connected successfully, {$migrationCount} migrations applied",
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => "Connection failed: {$e->getMessage()}",
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'deployment_health_' . time();
            $testValue = 'test_' . uniqid();
            
            Cache::put($testKey, $testValue, 10);
            $retrievedValue = Cache::get($testKey);
            Cache::forget($testKey);
            
            if ($retrievedValue !== $testValue) {
                throw new \Exception('Cache write/read verification failed');
            }
            
            return [
                'healthy' => true,
                'message' => 'Cache operations working correctly',
            ];
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => "Cache error: {$e->getMessage()}",
            ];
        }
    }

    private function checkSentry(): array
    {
        try {
            if (!config('sentry.dsn')) {
                return [
                    'healthy' => true,
                    'message' => 'Sentry not configured (optional)',
                ];
            }

            $sentryService = app(SentryService::class);
            $testResult = $sentryService->testIntegration();
            
            if ($testResult['overall_success']) {
                return [
                    'healthy' => true,
                    'message' => 'Sentry integration working',
                ];
            } else {
                return [
                    'healthy' => false,
                    'message' => 'Sentry integration test failed',
                ];
            }
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => "Sentry error: {$e->getMessage()}",
            ];
        }
    }

    private function checkFlagsmith(): array
    {
        try {
            $flagsmithService = app(FlagsmithService::class);
            
            if (!config('flagsmith.enabled')) {
                return [
                    'healthy' => true,
                    'message' => 'Flagsmith disabled (using defaults)',
                ];
            }
            
            $isHealthy = $flagsmithService->healthCheck();
            
            if ($isHealthy) {
                return [
                    'healthy' => true,
                    'message' => 'Flagsmith API accessible',
                ];
            } else {
                return [
                    'healthy' => false,
                    'message' => 'Flagsmith API not accessible (using fallbacks)',
                ];
            }
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => "Flagsmith error: {$e->getMessage()}",
            ];
        }
    }

    private function checkGrafana(): array
    {
        try {
            if (!config('monitoring.grafana.enabled')) {
                return [
                    'healthy' => true,
                    'message' => 'Grafana Cloud disabled',
                ];
            }

            $grafanaService = app(GrafanaCloudService::class);
            $isHealthy = $grafanaService->healthCheck();
            
            if ($isHealthy) {
                return [
                    'healthy' => true,
                    'message' => 'Grafana Cloud metrics endpoint accessible',
                ];
            } else {
                return [
                    'healthy' => false,
                    'message' => 'Grafana Cloud not accessible',
                ];
            }
        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => "Grafana error: {$e->getMessage()}",
            ];
        }
    }

    private function checkSSL(): array
    {
        try {
            $domain = config('app.url');
            $parsedUrl = parse_url($domain);
            $host = $parsedUrl['host'] ?? 'localhost';
            
            // Skip SSL check for localhost or IP addresses
            if (in_array($host, ['localhost', '127.0.0.1']) || filter_var($host, FILTER_VALIDATE_IP)) {
                return [
                    'healthy' => true,
                    'message' => 'SSL check skipped for localhost/IP',
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
                "ssl://{$host}:443",
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (!$socket) {
                return [
                    'healthy' => false,
                    'message' => "SSL connection failed: {$errstr}",
                ];
            }

            $cert = stream_context_get_params($socket)['options']['ssl']['peer_certificate'];
            fclose($socket);

            if (!$cert) {
                return [
                    'healthy' => false,
                    'message' => 'No SSL certificate found',
                ];
            }

            $certInfo = openssl_x509_parse($cert);
            $validTo = $certInfo['validTo_time_t'];
            $daysUntilExpiry = ($validTo - time()) / (24 * 60 * 60);

            if ($daysUntilExpiry < 0) {
                return [
                    'healthy' => false,
                    'message' => 'SSL certificate expired',
                ];
            } elseif ($daysUntilExpiry < 7) {
                return [
                    'healthy' => false,
                    'message' => "SSL certificate expires in {$daysUntilExpiry} days",
                ];
            } else {
                return [
                    'healthy' => true,
                    'message' => "SSL certificate valid for {$daysUntilExpiry} days",
                ];
            }

        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'message' => "SSL check error: {$e->getMessage()}",
            ];
        }
    }

    private function displayResultsTable(array $results, bool $overallHealthy): void
    {
        $tableData = [];
        
        foreach ($results as $service => $result) {
            $tableData[] = [
                'Service' => $service,
                'Status' => $result['healthy'] ? '✅ Healthy' : '❌ Unhealthy',
                'Message' => $result['message'],
                'Duration (ms)' => $result['duration_ms'] ?? 'N/A',
            ];
        }

        $this->table(['Service', 'Status', 'Message', 'Duration (ms)'], $tableData);
        
        $this->newLine();
        $this->info('Overall Status: ' . ($overallHealthy ? '✅ Healthy' : '❌ Unhealthy'));
    }
}