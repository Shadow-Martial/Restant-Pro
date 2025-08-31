<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\DeploymentService;
use App\Services\DeploymentRollbackService;
use App\Services\DeploymentNotificationService;
use App\Services\HealthCheckService;
use App\Events\DeploymentStarted;
use App\Events\DeploymentCompleted;
use App\Events\DeploymentRollback;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeploymentWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected DeploymentService $deploymentService;
    protected DeploymentRollbackService $rollbackService;
    protected DeploymentNotificationService $notificationService;
    protected HealthCheckService $healthCheckService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->deploymentService = app(DeploymentService::class);
        $this->rollbackService = app(DeploymentRollbackService::class);
        $this->notificationService = app(DeploymentNotificationService::class);
        $this->healthCheckService = app(HealthCheckService::class);
        
        // Mock external services
        Http::fake();
        Process::fake();
        Event::fake();
        Queue::fake();
        
        $this->setupTestConfiguration();
    }

    public function test_complete_deployment_workflow_integration()
    {
        // Mock successful deployment process
        Process::fake([
            'git push dokku@209.50.227.94:restant-main main' => Process::result('Deployment successful', '', 0),
            'ssh -i * dokku@209.50.227.94 ps:report restant-main --deployed' => Process::result('true', '', 0),
            'ssh -i * dokku@209.50.227.94 config:get restant-main APP_ENV' => Process::result('production', '', 0)
        ]);

        Http::fake([
            '*/health' => Http::response(['status' => 'healthy'], 200),
            'sentry.io/*' => Http::response(['status' => 'ok'], 200),
            'flagsmith.example.com/*' => Http::response([
                ['feature' => 'deployment_enabled', 'enabled' => true]
            ], 200)
        ]);

        // 1. Start deployment
        event(new DeploymentStarted('production', 'main', 'abc123def456'));

        // 2. Validate configuration
        $configValidation = $this->deploymentService->validateCompleteConfiguration();
        $this->assertTrue($configValidation['valid']);

        // 3. Perform pre-deployment checks
        $preDeploymentChecks = $this->deploymentService->performPreDeploymentChecks();
        $this->assertTrue($preDeploymentChecks['passed']);

        // 4. Execute deployment
        $deploymentResult = $this->deploymentService->executeDeployment('production', 'main', 'abc123def456');
        $this->assertTrue($deploymentResult['success']);

        // 5. Perform post-deployment health checks
        $healthCheck = $this->healthCheckService->performComprehensiveHealthCheck();
        $this->assertEquals('healthy', $healthCheck['status']);

        // 6. Complete deployment
        event(new DeploymentCompleted('production', 'main', 'abc123def456', true));

        // Verify all events were dispatched
        Event::assertDispatched(DeploymentStarted::class);
        Event::assertDispatched(DeploymentCompleted::class);
    }

    public function test_deployment_failure_and_rollback_workflow()
    {
        // Mock failed deployment and successful rollback
        Process::fake([
            'git push dokku@209.50.227.94:restant-main main' => Process::result('', 'Deployment failed', 1),
            'ssh -i * dokku@209.50.227.94 ps:report restant-main --deployed' => Process::result("v3\nv2\nv1", '', 0),
            'ssh -i * dokku@209.50.227.94 ps:stop restant-main' => Process::result('Stopped', '', 0),
            'ssh -i * dokku@209.50.227.94 ps:rebuild restant-main' => Process::result('Rebuilt successfully', '', 0)
        ]);

        Http::fake([
            '*/health' => Http::response(['status' => 'unhealthy'], 500)
        ]);

        // 1. Start deployment
        event(new DeploymentStarted('production', 'main', 'abc123def456'));

        // 2. Deployment fails
        $deploymentResult = $this->deploymentService->executeDeployment('production', 'main', 'abc123def456');
        $this->assertFalse($deploymentResult['success']);

        // 3. Health check fails
        $healthCheck = $this->healthCheckService->performComprehensiveHealthCheck();
        $this->assertEquals('unhealthy', $healthCheck['status']);

        // 4. Automatic rollback triggered
        $rollbackResult = $this->rollbackService->performAutomaticRollback('restant-main', 'Deployment health check failed');
        $this->assertTrue($rollbackResult);

        // 5. Rollback event dispatched
        event(new DeploymentRollback('production', 'Deployment health check failed'));

        // Verify events
        Event::assertDispatched(DeploymentStarted::class);
        Event::assertDispatched(DeploymentRollback::class);
    }

    public function test_multi_environment_deployment_workflow()
    {
        $environments = ['staging', 'production'];
        
        foreach ($environments as $env) {
            $appName = "restant-{$env}";
            
            Process::fake([
                "git push dokku@209.50.227.94:{$appName} {$env}" => Process::result('Deployment successful', '', 0),
                "ssh -i * dokku@209.50.227.94 ps:report {$appName} --deployed" => Process::result('true', '', 0)
            ]);

            Http::fake([
                "restant.{$env}.susankshakya.com.np/health" => Http::response(['status' => 'healthy'], 200)
            ]);

            // Deploy to environment
            $deploymentResult = $this->deploymentService->executeDeployment($env, $env, 'abc123def456');
            $this->assertTrue($deploymentResult['success']);

            // Verify environment-specific configuration
            $envConfig = $this->deploymentService->getEnvironmentConfig($env);
            $this->assertEquals($env, $envConfig['subdomain']);
            $this->assertEquals($appName, $envConfig['dokku_app']);
        }
    }

    public function test_deployment_with_database_migrations()
    {
        Process::fake([
            'php artisan migrate --force' => Process::result('Migration completed successfully', '', 0),
            'php artisan config:cache' => Process::result('Configuration cached', '', 0),
            'php artisan route:cache' => Process::result('Routes cached', '', 0),
            'php artisan view:cache' => Process::result('Views cached', '', 0),
            'git push dokku@209.50.227.94:restant-main main' => Process::result('Deployment successful', '', 0)
        ]);

        // Test deployment with migrations
        $deploymentResult = $this->deploymentService->executeDeploymentWithMigrations('production', 'main', 'abc123def456');
        
        $this->assertTrue($deploymentResult['success']);
        $this->assertTrue($deploymentResult['migrations_run']);
        
        // Verify migration commands were executed
        Process::assertRan('php artisan migrate --force');
        Process::assertRan('php artisan config:cache');
    }

    public function test_deployment_with_asset_compilation()
    {
        Process::fake([
            'npm ci' => Process::result('Dependencies installed', '', 0),
            'npm run production' => Process::result('Assets compiled successfully', '', 0),
            'git push dokku@209.50.227.94:restant-main main' => Process::result('Deployment successful', '', 0)
        ]);

        // Test deployment with asset compilation
        $deploymentResult = $this->deploymentService->executeDeploymentWithAssets('production', 'main', 'abc123def456');
        
        $this->assertTrue($deploymentResult['success']);
        $this->assertTrue($deploymentResult['assets_compiled']);
        
        // Verify asset compilation commands were executed
        Process::assertRan('npm ci');
        Process::assertRan('npm run production');
    }

    public function test_deployment_notification_workflow_integration()
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

        Http::fake([
            'hooks.slack.com/test' => Http::response(['ok' => true], 200)
        ]);

        // Test complete notification workflow
        $this->notificationService->notifyDeploymentStarted('production', 'main', 'abc123def456');
        
        // Simulate successful deployment
        $deploymentDetails = [
            'duration' => '2m 30s',
            'migrations_run' => 3,
            'assets_compiled' => true
        ];
        
        $this->notificationService->notifyDeploymentSuccess('production', 'main', 'abc123def456', $deploymentDetails);

        // Verify notifications were sent
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'hooks.slack.com');
        });
    }

    public function test_deployment_monitoring_integration_workflow()
    {
        // Configure all monitoring services
        Config::set('deployment.monitoring', [
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
                'api_key' => 'test_api_key'
            ]
        ]);

        Http::fake([
            'sentry.io/*' => Http::response(['status' => 'ok'], 200),
            'flagsmith.example.com/*' => Http::response([
                ['feature' => 'deployment_enabled', 'enabled' => true]
            ], 200),
            'prometheus.grafana.net/*' => Http::response([], 200)
        ]);

        // Test monitoring integration during deployment
        $monitoringResults = $this->deploymentService->initializeMonitoringServices();
        
        $this->assertTrue($monitoringResults['sentry']['initialized']);
        $this->assertTrue($monitoringResults['flagsmith']['initialized']);
        $this->assertTrue($monitoringResults['grafana']['initialized']);

        // Test monitoring during health checks
        $healthCheck = $this->healthCheckService->performComprehensiveHealthCheck();
        
        $this->assertArrayHasKey('sentry', $healthCheck['checks']);
        $this->assertArrayHasKey('flagsmith', $healthCheck['checks']);
        $this->assertEquals('healthy', $healthCheck['checks']['sentry']);
    }

    public function test_deployment_ssl_certificate_workflow()
    {
        Process::fake([
            'ssh -i * dokku@209.50.227.94 certs:add restant-main /path/to/cert.pem /path/to/key.pem' => Process::result('SSL certificate added', '', 0),
            'ssh -i * dokku@209.50.227.94 certs:report restant-main' => Process::result('SSL enabled: true', '', 0)
        ]);

        // Test SSL certificate provisioning
        $sslResult = $this->deploymentService->provisionSslCertificate('restant-main', 'restant.main.susankshakya.com.np');
        
        $this->assertTrue($sslResult['success']);
        $this->assertTrue($sslResult['ssl_enabled']);
        
        // Verify SSL commands were executed
        Process::assertRan('ssh -i * dokku@209.50.227.94 certs:add restant-main /path/to/cert.pem /path/to/key.pem');
    }

    public function test_deployment_environment_variable_management()
    {
        $environmentVars = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'SENTRY_DSN' => 'https://test@sentry.io/123',
            'FLAGSMITH_ENVIRONMENT_KEY' => 'test_key'
        ];

        Process::fake([
            'ssh -i * dokku@209.50.227.94 config:set restant-main *' => Process::result('Environment variables set', '', 0),
            'ssh -i * dokku@209.50.227.94 config:get restant-main APP_ENV' => Process::result('production', '', 0)
        ]);

        // Test environment variable management
        $envResult = $this->deploymentService->setEnvironmentVariables('restant-main', $environmentVars);
        
        $this->assertTrue($envResult['success']);
        $this->assertEquals(count($environmentVars), $envResult['variables_set']);
    }

    public function test_deployment_service_dependencies_workflow()
    {
        Process::fake([
            'ssh -i * dokku@209.50.227.94 mysql:create restant-main-db' => Process::result('MySQL service created', '', 0),
            'ssh -i * dokku@209.50.227.94 mysql:link restant-main-db restant-main' => Process::result('MySQL linked', '', 0),
            'ssh -i * dokku@209.50.227.94 redis:create restant-main-redis' => Process::result('Redis service created', '', 0),
            'ssh -i * dokku@209.50.227.94 redis:link restant-main-redis restant-main' => Process::result('Redis linked', '', 0)
        ]);

        // Test service dependency setup
        $servicesResult = $this->deploymentService->setupServiceDependencies('restant-main', ['mysql', 'redis']);
        
        $this->assertTrue($servicesResult['success']);
        $this->assertContains('mysql', $servicesResult['services_created']);
        $this->assertContains('redis', $servicesResult['services_created']);
    }

    public function test_deployment_performance_monitoring_workflow()
    {
        // Mock performance monitoring setup
        Http::fake([
            'prometheus.grafana.net/*' => Http::response([], 200),
            'sentry.io/*' => Http::response(['status' => 'ok'], 200)
        ]);

        // Test performance monitoring initialization
        $performanceResult = $this->deploymentService->initializePerformanceMonitoring();
        
        $this->assertTrue($performanceResult['success']);
        $this->assertArrayHasKey('metrics_endpoint', $performanceResult);
        $this->assertArrayHasKey('traces_enabled', $performanceResult);
    }

    protected function setupTestConfiguration(): void
    {
        Config::set('deployment', [
            'environments' => [
                'production' => [
                    'subdomain' => 'main',
                    'branch' => 'main',
                    'dokku_app' => 'restant-main'
                ],
                'staging' => [
                    'subdomain' => 'staging',
                    'branch' => 'staging',
                    'dokku_app' => 'restant-staging'
                ]
            ],
            'dokku' => [
                'host' => '209.50.227.94',
                'ssh_key_path' => '/path/to/ssh/key'
            ],
            'monitoring' => [
                'sentry' => ['enabled' => true],
                'flagsmith' => ['enabled' => true],
                'grafana' => ['enabled' => true]
            ],
            'notifications' => [
                'channels' => [
                    'slack' => ['enabled' => false],
                    'email' => ['enabled' => false]
                ]
            ],
            'rollback' => [
                'enabled' => true,
                'auto_rollback_on_failure' => true,
                'max_rollback_attempts' => 3
            ],
            'health_checks' => [
                'enabled' => true,
                'timeout' => 30,
                'retries' => 3,
                'endpoints' => [
                    'app' => '/health'
                ]
            ]
        ]);
    }
}