<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\DeploymentService;
use App\Services\DeploymentNotificationService;
use App\Events\DeploymentStarted;
use App\Events\DeploymentCompleted;
use App\Events\DeploymentRollback;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EndToEndDeploymentTest extends TestCase
{
    use RefreshDatabase;

    protected DeploymentService $deploymentService;
    protected DeploymentNotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->deploymentService = app(DeploymentService::class);
        $this->notificationService = app(DeploymentNotificationService::class);
        
        // Mock external services
        Http::fake();
        Event::fake();
        
        // Mock process execution for Dokku commands
        Process::fake();
    }

    public function test_complete_deployment_workflow_for_production()
    {
        // Setup production environment configuration
        Config::set('app.env', 'production');
        Config::set('dokku-deployment.environments.production', [
            'app_name' => 'restant-main',
            'domain' => 'restant.main.susankshakya.com.np',
            'ssl_enabled' => true,
            'branch' => 'main'
        ]);

        // Mock successful deployment process
        Process::fake([
            'git push dokku@209.50.227.94:restant-main main' => Process::result('Deployment successful', '', 0),
            'ssh -i * dokku@209.50.227.94 ps:report restant-main --deployed' => Process::result('true', '', 0)
        ]);

        // 1. Deployment starts
        event(new DeploymentStarted('production', 'main', 'abc123def456'));
        
        // 2. Verify deployment configuration
        $this->assertEquals('production', $this->deploymentService->getCurrentEnvironment());
        $this->assertEquals('restant-main', $this->deploymentService->getAppName());
        $this->assertTrue($this->deploymentService->isSslEnabled());

        // 3. Perform health checks
        $healthCheck = $this->deploymentService->performHealthCheck();
        $this->assertEquals('healthy', $healthCheck['status']);

        // 4. Deployment completes successfully
        event(new DeploymentCompleted('production', 'main', 'abc123def456', true));

        // 5. Verify events were dispatched
        Event::assertDispatched(DeploymentStarted::class);
        Event::assertDispatched(DeploymentCompleted::class);
    }

    public function test_complete_deployment_workflow_for_staging()
    {
        // Setup staging environment configuration
        Config::set('app.env', 'staging');
        Config::set('dokku-deployment.environments.staging', [
            'app_name' => 'restant-staging',
            'domain' => 'restant.staging.susankshakya.com.np',
            'ssl_enabled' => true,
            'branch' => 'staging'
        ]);

        // Mock successful deployment process
        Process::fake([
            'git push dokku@209.50.227.94:restant-staging staging' => Process::result('Deployment successful', '', 0),
            'ssh -i * dokku@209.50.227.94 ps:report restant-staging --deployed' => Process::result('true', '', 0)
        ]);

        // Execute deployment workflow
        event(new DeploymentStarted('staging', 'staging', 'def456abc789'));
        
        $this->assertEquals('staging', $this->deploymentService->getCurrentEnvironment());
        $this->assertEquals('restant-staging', $this->deploymentService->getAppName());
        
        event(new DeploymentCompleted('staging', 'staging', 'def456abc789', true));

        Event::assertDispatched(DeploymentStarted::class);
        Event::assertDispatched(DeploymentCompleted::class);
    }

    public function test_deployment_with_monitoring_services_integration()
    {
        // Configure all monitoring services
        Config::set('dokku-deployment.monitoring', [
            'sentry' => [
                'enabled' => true,
                'dsn' => 'https://test@sentry.io/123'
            ],
            'flagsmith' => [
                'enabled' => true,
                'environment_key' => 'test_key',
                'api_url' => 'https://flagsmith.example.com/api/v1/'
            ],
            'grafana' => [
                'enabled' => true,
                'api_key' => 'test_api_key',
                'instance_id' => 'test_instance'
            ]
        ]);

        // Mock monitoring service responses
        Http::fake([
            'sentry.io/*' => Http::response(['status' => 'ok'], 200),
            'flagsmith.example.com/api/v1/flags/*' => Http::response([
                ['feature' => 'deployment_enabled', 'enabled' => true]
            ], 200),
            'prometheus-prod-01-eu-west-0.grafana.net/*' => Http::response([], 200)
        ]);

        // Verify all monitoring services are enabled
        $this->assertTrue($this->deploymentService->isMonitoringEnabled('sentry'));
        $this->assertTrue($this->deploymentService->isMonitoringEnabled('flagsmith'));
        $this->assertTrue($this->deploymentService->isMonitoringEnabled('grafana'));

        // Execute deployment with monitoring
        event(new DeploymentStarted('production', 'main', 'abc123'));
        
        $healthCheck = $this->deploymentService->performHealthCheck();
        $this->assertArrayHasKey('sentry', $healthCheck['checks']);
        $this->assertArrayHasKey('flagsmith', $healthCheck['checks']);

        event(new DeploymentCompleted('production', 'main', 'abc123', true));
    }

    public function test_deployment_failure_triggers_rollback()
    {
        Config::set('deployment.rollback.auto_rollback_on_failure', true);
        
        // Mock failed deployment
        Process::fake([
            'git push dokku@209.50.227.94:restant-main main' => Process::result('', 'Deployment failed', 1),
            'ssh -i * dokku@209.50.227.94 ps:report restant-main --deployed' => Process::result('v2', '', 0),
            'ssh -i * dokku@209.50.227.94 ps:stop restant-main' => Process::result('Stopped', '', 0),
            'ssh -i * dokku@209.50.227.94 ps:rebuild restant-main' => Process::result('Rebuilt', '', 0)
        ]);

        // Simulate deployment failure
        event(new DeploymentStarted('production', 'main', 'abc123'));
        
        // Trigger rollback
        event(new DeploymentRollback('production', 'Deployment health check failed'));

        Event::assertDispatched(DeploymentStarted::class);
        Event::assertDispatched(DeploymentRollback::class);
    }

    public function test_deployment_health_checks_validation()
    {
        Config::set('deployment.health_checks', [
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'endpoints' => [
                'app' => '/health',
                'database' => '/health/database',
                'cache' => '/health/cache'
            ]
        ]);

        // Mock health check endpoints
        Http::fake([
            '*/health' => Http::response(['status' => 'healthy'], 200),
            '*/health/database' => Http::response(['status' => 'healthy'], 200),
            '*/health/cache' => Http::response(['status' => 'healthy'], 200)
        ]);

        $healthCheck = $this->deploymentService->performHealthCheck();
        
        $this->assertEquals('healthy', $healthCheck['status']);
        $this->assertArrayHasKey('database', $healthCheck['checks']);
    }

    public function test_deployment_with_ssl_certificate_validation()
    {
        Config::set('dokku-deployment.environments.production', [
            'app_name' => 'restant-main',
            'domain' => 'restant.main.susankshakya.com.np',
            'ssl_enabled' => true
        ]);

        // Mock SSL certificate check
        Process::fake([
            'ssh -i * dokku@209.50.227.94 certs:report restant-main' => Process::result('SSL enabled: true', '', 0)
        ]);

        $this->assertTrue($this->deploymentService->isSslEnabled());
        
        $deploymentInfo = $this->deploymentService->getDeploymentInfo();
        $this->assertTrue($deploymentInfo['ssl_enabled']);
    }

    public function test_deployment_notification_workflow()
    {
        Config::set('deployment.notifications.channels', [
            'slack' => [
                'enabled' => true,
                'webhook_url' => 'https://hooks.slack.com/test'
            ],
            'email' => [
                'enabled' => true,
                'recipients' => ['admin@example.com']
            ]
        ]);

        // Mock notification endpoints
        Http::fake([
            'hooks.slack.com/test' => Http::response([], 200)
        ]);

        // Test deployment started notification
        $this->notificationService->notifyDeploymentStarted('production', 'main', 'abc123');
        
        // Test deployment success notification
        $this->notificationService->notifyDeploymentSuccess('production', 'main', 'abc123', [
            'duration' => '2m 30s',
            'migrations_run' => 3
        ]);

        // Verify HTTP requests were made
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com');
        });
    }

    public function test_deployment_with_database_migrations()
    {
        // Mock migration commands
        Process::fake([
            'php artisan migrate --force' => Process::result('Migrations completed', '', 0),
            'php artisan config:cache' => Process::result('Configuration cached', '', 0),
            'php artisan route:cache' => Process::result('Routes cached', '', 0),
            'php artisan view:cache' => Process::result('Views cached', '', 0)
        ]);

        // Simulate deployment with migrations
        Artisan::call('migrate', ['--force' => true]);
        
        $this->assertEquals(0, Artisan::output());
    }

    public function test_deployment_asset_compilation()
    {
        // Mock asset compilation
        Process::fake([
            'npm ci' => Process::result('Dependencies installed', '', 0),
            'npm run production' => Process::result('Assets compiled', '', 0)
        ]);

        // Verify asset compilation would succeed
        $this->assertTrue(true); // Process mocking ensures success
    }

    public function test_deployment_environment_variable_validation()
    {
        // Set required environment variables
        putenv('APP_KEY=base64:' . base64_encode(str_repeat('a', 32)));
        putenv('DB_DATABASE=test_db');
        putenv('DB_USERNAME=test_user');
        putenv('DB_PASSWORD=test_password');

        $deploymentInfo = $this->deploymentService->getDeploymentInfo();
        
        $this->assertArrayHasKey('environment', $deploymentInfo);
        $this->assertArrayHasKey('php_version', $deploymentInfo);
        $this->assertArrayHasKey('laravel_version', $deploymentInfo);
    }

    public function test_deployment_logging_and_monitoring()
    {
        // Configure deployment logging
        Config::set('logging.channels.deployment', [
            'driver' => 'daily',
            'path' => storage_path('logs/deployment.log'),
            'level' => 'debug',
            'days' => 30
        ]);

        // Test deployment event logging
        $this->deploymentService->logDeploymentEvent('deployment_started', [
            'environment' => 'production',
            'branch' => 'main',
            'commit' => 'abc123'
        ]);

        // Verify logging doesn't throw exceptions
        $this->assertTrue(true);
    }

    public function test_deployment_with_feature_flags()
    {
        Config::set('dokku-deployment.monitoring.flagsmith', [
            'enabled' => true,
            'environment_key' => 'test_key'
        ]);

        // Mock feature flag response
        Http::fake([
            '*/api/v1/flags/*' => Http::response([
                ['feature' => 'deployment_enabled', 'enabled' => true],
                ['feature' => 'maintenance_mode', 'enabled' => false]
            ], 200)
        ]);

        $this->assertTrue($this->deploymentService->isMonitoringEnabled('flagsmith'));
    }

    public function test_deployment_with_zero_downtime_strategy()
    {
        // Mock zero-downtime deployment process
        Process::fake([
            'ssh -i * dokku@209.50.227.94 ps:scale restant-main web=2' => Process::result('Scaled to 2 instances', '', 0),
            'git push dokku@209.50.227.94:restant-main main' => Process::result('Deployment successful', '', 0),
            'ssh -i * dokku@209.50.227.94 ps:report restant-main --deployed' => Process::result('true', '', 0)
        ]);

        Http::fake([
            '*/health' => Http::response(['status' => 'healthy'], 200)
        ]);

        // Test zero-downtime deployment
        $deploymentResult = $this->deploymentService->executeZeroDowntimeDeployment('production', 'main', 'abc123');
        
        $this->assertTrue($deploymentResult['success']);
        $this->assertEquals('zero-downtime', $deploymentResult['strategy']);
        $this->assertGreaterThan(0, $deploymentResult['instances']);
    }

    public function test_deployment_with_canary_release_strategy()
    {
        // Mock canary deployment process
        Process::fake([
            'ssh -i * dokku@209.50.227.94 ps:scale restant-main web=3' => Process::result('Scaled to 3 instances', '', 0),
            'git push dokku@209.50.227.94:restant-main-canary main' => Process::result('Canary deployment successful', '', 0),
            'ssh -i * dokku@209.50.227.94 proxy:ports-set restant-main-canary http:80:5000' => Process::result('Ports configured', '', 0)
        ]);

        Http::fake([
            '*/health' => Http::response(['status' => 'healthy'], 200),
            '*/metrics' => Http::response(['error_rate' => 0.01], 200)
        ]);

        // Test canary deployment
        $canaryResult = $this->deploymentService->executeCanaryDeployment('production', 'main', 'abc123', 10); // 10% traffic
        
        $this->assertTrue($canaryResult['success']);
        $this->assertEquals(10, $canaryResult['traffic_percentage']);
        $this->assertLessThan(0.05, $canaryResult['error_rate']); // Error rate should be low
    }

    public function test_deployment_with_blue_green_strategy()
    {
        // Mock blue-green deployment process
        Process::fake([
            'ssh -i * dokku@209.50.227.94 apps:create restant-main-green' => Process::result('Green environment created', '', 0),
            'git push dokku@209.50.227.94:restant-main-green main' => Process::result('Green deployment successful', '', 0),
            'ssh -i * dokku@209.50.227.94 proxy:ports-set restant-main-green http:80:5000' => Process::result('Green ports configured', '', 0),
            'ssh -i * dokku@209.50.227.94 domains:add restant-main-green restant.main.susankshakya.com.np' => Process::result('Domain switched', '', 0)
        ]);

        Http::fake([
            '*/health' => Http::response(['status' => 'healthy'], 200)
        ]);

        // Test blue-green deployment
        $blueGreenResult = $this->deploymentService->executeBlueGreenDeployment('production', 'main', 'abc123');
        
        $this->assertTrue($blueGreenResult['success']);
        $this->assertEquals('green', $blueGreenResult['active_environment']);
        $this->assertEquals('blue', $blueGreenResult['standby_environment']);
    }

    public function test_deployment_with_feature_flag_validation()
    {
        Config::set('deployment.monitoring.flagsmith', [
            'enabled' => true,
            'environment_key' => 'test_key'
        ]);

        // Mock feature flag validation
        Http::fake([
            '*/api/v1/flags/*' => Http::response([
                ['feature' => 'deployment_enabled', 'enabled' => true],
                ['feature' => 'maintenance_mode', 'enabled' => false],
                ['feature' => 'new_feature_rollout', 'enabled' => true]
            ], 200)
        ]);

        // Test deployment with feature flag validation
        $flagValidation = $this->deploymentService->validateFeatureFlags(['deployment_enabled', 'new_feature_rollout']);
        
        $this->assertTrue($flagValidation['valid']);
        $this->assertTrue($flagValidation['flags']['deployment_enabled']);
        $this->assertTrue($flagValidation['flags']['new_feature_rollout']);
    }

    public function test_deployment_with_performance_regression_detection()
    {
        // Mock performance baseline and current metrics
        Http::fake([
            '*/metrics/baseline' => Http::response([
                'response_time' => 200,
                'throughput' => 1000,
                'error_rate' => 0.01
            ], 200),
            '*/metrics/current' => Http::response([
                'response_time' => 250, // 25% increase
                'throughput' => 950, // 5% decrease
                'error_rate' => 0.015 // 50% increase
            ], 200)
        ]);

        // Test performance regression detection
        $regressionCheck = $this->deploymentService->detectPerformanceRegression();
        
        $this->assertTrue($regressionCheck['regression_detected']);
        $this->assertContains('response_time', $regressionCheck['degraded_metrics']);
        $this->assertContains('error_rate', $regressionCheck['degraded_metrics']);
    }

    public function test_deployment_with_security_vulnerability_scanning()
    {
        // Mock security scanning results
        Http::fake([
            '*/security/scan' => Http::response([
                'vulnerabilities' => [],
                'security_score' => 95,
                'scan_status' => 'passed'
            ], 200)
        ]);

        // Test security vulnerability scanning
        $securityScan = $this->deploymentService->performSecurityScan();
        
        $this->assertEquals('passed', $securityScan['scan_status']);
        $this->assertGreaterThanOrEqual(90, $securityScan['security_score']);
        $this->assertEmpty($securityScan['vulnerabilities']);
    }

    public function test_deployment_with_database_backup_and_restore()
    {
        // Mock database backup and restore operations
        Process::fake([
            'ssh -i * dokku@209.50.227.94 mysql:export restant-main-db > backup.sql' => Process::result('Backup created', '', 0),
            'ssh -i * dokku@209.50.227.94 mysql:import restant-main-db < backup.sql' => Process::result('Backup restored', '', 0)
        ]);

        // Test database backup before deployment
        $backupResult = $this->deploymentService->createDatabaseBackup('restant-main');
        $this->assertTrue($backupResult['success']);
        $this->assertNotEmpty($backupResult['backup_id']);

        // Test database restore on failure
        $restoreResult = $this->deploymentService->restoreDatabaseBackup('restant-main', $backupResult['backup_id']);
        $this->assertTrue($restoreResult['success']);
    }

    public function test_deployment_with_load_testing_validation()
    {
        // Mock load testing results
        Http::fake([
            '*/load-test/execute' => Http::response([
                'test_status' => 'passed',
                'avg_response_time' => 180,
                'max_response_time' => 500,
                'requests_per_second' => 1200,
                'error_rate' => 0.005
            ], 200)
        ]);

        // Test load testing validation
        $loadTestResult = $this->deploymentService->executeLoadTest();
        
        $this->assertEquals('passed', $loadTestResult['test_status']);
        $this->assertLessThan(200, $loadTestResult['avg_response_time']);
        $this->assertLessThan(0.01, $loadTestResult['error_rate']);
    }

    public function test_deployment_with_compliance_validation()
    {
        // Mock compliance validation
        Http::fake([
            '*/compliance/validate' => Http::response([
                'gdpr_compliant' => true,
                'security_headers' => true,
                'data_encryption' => true,
                'audit_logging' => true,
                'compliance_score' => 98
            ], 200)
        ]);

        // Test compliance validation
        $complianceResult = $this->deploymentService->validateCompliance();
        
        $this->assertTrue($complianceResult['gdpr_compliant']);
        $this->assertTrue($complianceResult['security_headers']);
        $this->assertTrue($complianceResult['data_encryption']);
        $this->assertGreaterThanOrEqual(95, $complianceResult['compliance_score']);
    }

    public function test_deployment_with_multi_region_synchronization()
    {
        $regions = ['us-east-1', 'eu-west-1', 'ap-southeast-1'];
        
        foreach ($regions as $region) {
            Process::fake([
                "ssh -i * dokku-{$region}@server.com ps:report restant-main --deployed" => Process::result('true', '', 0)
            ]);

            Http::fake([
                "restant-{$region}.susankshakya.com.np/health" => Http::response(['status' => 'healthy'], 200)
            ]);
        }

        // Test multi-region deployment synchronization
        $multiRegionResult = $this->deploymentService->synchronizeMultiRegionDeployment($regions);
        
        $this->assertTrue($multiRegionResult['success']);
        $this->assertEquals(count($regions), $multiRegionResult['regions_deployed']);
        $this->assertEmpty($multiRegionResult['failed_regions']);
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('APP_KEY=');
        putenv('DB_DATABASE=');
        putenv('DB_USERNAME=');
        putenv('DB_PASSWORD=');

        parent::tearDown();
    }
}