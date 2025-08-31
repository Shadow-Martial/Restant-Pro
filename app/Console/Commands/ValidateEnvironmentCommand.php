<?php

namespace App\Console\Commands;

use App\Services\EnvironmentManager;
use App\Services\SecretManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;

class ValidateEnvironmentCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'env:validate 
                            {--environment= : The environment to validate (defaults to current)}
                            {--fix : Attempt to fix configuration issues}
                            {--services : Test external service connections}';

    /**
     * The console command description.
     */
    protected $description = 'Validate environment configuration and dependencies';

    /**
     * Environment manager instance
     */
    protected EnvironmentManager $environmentManager;

    /**
     * Secret manager instance
     */
    protected SecretManager $secretManager;

    /**
     * Create a new command instance.
     */
    public function __construct(EnvironmentManager $environmentManager, SecretManager $secretManager)
    {
        parent::__construct();
        $this->environmentManager = $environmentManager;
        $this->secretManager = $secretManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $environment = $this->option('environment') ?? $this->environmentManager->getCurrentEnvironment();
        $fix = $this->option('fix');
        $testServices = $this->option('services');

        $this->info("Validating environment configuration for: {$environment}");
        $this->newLine();

        $errors = [];
        $warnings = [];

        // Validate basic environment configuration
        $configErrors = $this->validateConfiguration($environment);
        $errors = array_merge($errors, $configErrors);

        // Validate database connection
        $dbErrors = $this->validateDatabase();
        $errors = array_merge($errors, $dbErrors);

        // Validate cache connection
        $cacheErrors = $this->validateCache();
        $errors = array_merge($errors, $cacheErrors);

        // Validate external services if requested
        if ($testServices) {
            $serviceErrors = $this->validateExternalServices();
            $errors = array_merge($errors, $serviceErrors);
        }

        // Validate security settings
        $securityWarnings = $this->validateSecurity($environment);
        $warnings = array_merge($warnings, $securityWarnings);

        // Validate performance settings
        $performanceWarnings = $this->validatePerformance($environment);
        $warnings = array_merge($warnings, $performanceWarnings);

        // Display results
        $this->displayResults($errors, $warnings, $fix);

        return empty($errors) ? 0 : 1;
    }

    /**
     * Validate basic configuration
     */
    protected function validateConfiguration(string $environment): array
    {
        $this->info('🔍 Validating basic configuration...');
        
        $errors = $this->environmentManager->validateEnvironmentConfig($environment);

        if (empty($errors)) {
            $this->info('✅ Basic configuration is valid');
        } else {
            $this->error('❌ Configuration validation failed');
            foreach ($errors as $error) {
                $this->line("   - {$error}");
            }
        }

        return $errors;
    }

    /**
     * Validate database connection
     */
    protected function validateDatabase(): array
    {
        $this->info('🔍 Validating database connection...');
        $errors = [];

        try {
            DB::connection()->getPdo();
            $this->info('✅ Database connection successful');

            // Test a simple query
            $result = DB::select('SELECT 1 as test');
            if (empty($result)) {
                $errors[] = 'Database query test failed';
            }
        } catch (\Exception $e) {
            $errors[] = "Database connection failed: {$e->getMessage()}";
            $this->error('❌ Database connection failed');
        }

        return $errors;
    }

    /**
     * Validate cache connection
     */
    protected function validateCache(): array
    {
        $this->info('🔍 Validating cache connection...');
        $errors = [];

        try {
            $cacheDriver = config('cache.default');
            
            if ($cacheDriver === 'redis') {
                Redis::ping();
                $this->info('✅ Redis connection successful');

                // Test cache operations
                cache()->put('test_key', 'test_value', 60);
                $value = cache()->get('test_key');
                
                if ($value !== 'test_value') {
                    $errors[] = 'Cache read/write test failed';
                } else {
                    cache()->forget('test_key');
                }
            } else {
                $this->info("✅ Cache driver '{$cacheDriver}' is configured");
            }
        } catch (\Exception $e) {
            $errors[] = "Cache connection failed: {$e->getMessage()}";
            $this->error('❌ Cache connection failed');
        }

        return $errors;
    }

    /**
     * Validate external services
     */
    protected function validateExternalServices(): array
    {
        $this->info('🔍 Validating external services...');
        $errors = [];

        // Test Sentry
        if (config('deployment.monitoring.sentry.enabled')) {
            try {
                $dsn = env('SENTRY_LARAVEL_DSN');
                if ($dsn) {
                    // Parse DSN to get the host
                    $parsed = parse_url($dsn);
                    $host = $parsed['host'] ?? null;
                    
                    if ($host) {
                        $response = Http::timeout(10)->get("https://{$host}");
                        if ($response->successful()) {
                            $this->info('✅ Sentry service is reachable');
                        } else {
                            $errors[] = "Sentry service returned status: {$response->status()}";
                        }
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Sentry validation failed: {$e->getMessage()}";
            }
        }

        // Test Flagsmith
        if (config('deployment.monitoring.flagsmith.enabled')) {
            try {
                $apiUrl = env('FLAGSMITH_API_URL');
                if ($apiUrl) {
                    $response = Http::timeout(10)->get($apiUrl);
                    if ($response->successful()) {
                        $this->info('✅ Flagsmith service is reachable');
                    } else {
                        $errors[] = "Flagsmith service returned status: {$response->status()}";
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Flagsmith validation failed: {$e->getMessage()}";
            }
        }

        // Test Grafana Cloud
        if (config('deployment.monitoring.grafana.enabled')) {
            try {
                $instanceId = env('GRAFANA_CLOUD_INSTANCE_ID');
                if ($instanceId) {
                    $response = Http::timeout(10)->get("https://{$instanceId}.grafana.net/api/health");
                    if ($response->successful()) {
                        $this->info('✅ Grafana Cloud service is reachable');
                    } else {
                        $errors[] = "Grafana Cloud service returned status: {$response->status()}";
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Grafana Cloud validation failed: {$e->getMessage()}";
            }
        }

        return $errors;
    }

    /**
     * Validate security settings
     */
    protected function validateSecurity(string $environment): array
    {
        $this->info('🔍 Validating security settings...');
        $warnings = [];

        if ($environment === 'production') {
            if (config('app.debug')) {
                $warnings[] = 'APP_DEBUG should be false in production';
            }

            if (!config('app.url') || !str_starts_with(config('app.url'), 'https://')) {
                $warnings[] = 'APP_URL should use HTTPS in production';
            }

            if (!env('APP_KEY')) {
                $warnings[] = 'APP_KEY is not set';
            }
        }

        if (empty($warnings)) {
            $this->info('✅ Security settings look good');
        } else {
            $this->warn('⚠️  Security warnings found');
        }

        return $warnings;
    }

    /**
     * Validate performance settings
     */
    protected function validatePerformance(string $environment): array
    {
        $this->info('🔍 Validating performance settings...');
        $warnings = [];

        if ($environment === 'production') {
            if (config('cache.default') === 'file') {
                $warnings[] = 'Consider using Redis for better cache performance in production';
            }

            if (config('session.driver') === 'file') {
                $warnings[] = 'Consider using Redis for session storage in production';
            }

            // Check if OPcache is enabled
            if (!extension_loaded('opcache') || !ini_get('opcache.enable')) {
                $warnings[] = 'OPcache is not enabled - consider enabling for better performance';
            }
        }

        if (empty($warnings)) {
            $this->info('✅ Performance settings look good');
        } else {
            $this->warn('⚠️  Performance optimization suggestions available');
        }

        return $warnings;
    }

    /**
     * Display validation results
     */
    protected function displayResults(array $errors, array $warnings, bool $fix): void
    {
        $this->newLine();

        if (empty($errors) && empty($warnings)) {
            $this->info('🎉 All validations passed! Your environment is properly configured.');
            return;
        }

        if (!empty($errors)) {
            $this->error('❌ Validation Errors:');
            foreach ($errors as $error) {
                $this->line("   - {$error}");
            }
            $this->newLine();
        }

        if (!empty($warnings)) {
            $this->warn('⚠️  Warnings:');
            foreach ($warnings as $warning) {
                $this->line("   - {$warning}");
            }
            $this->newLine();
        }

        if ($fix && !empty($errors)) {
            $this->info('🔧 Attempting to fix issues...');
            $this->attemptFixes($errors);
        }

        // Provide suggestions
        $this->info('💡 Suggestions:');
        $this->line('   - Run with --fix to attempt automatic fixes');
        $this->line('   - Run with --services to test external service connections');
        $this->line('   - Check your .env file for missing or incorrect values');
        $this->line('   - Use php artisan secrets:manage to handle sensitive configuration');
    }

    /**
     * Attempt to fix common issues
     */
    protected function attemptFixes(array $errors): void
    {
        foreach ($errors as $error) {
            if (str_contains($error, 'APP_KEY')) {
                $this->info('Generating new APP_KEY...');
                $this->call('key:generate');
            }
        }
    }
}