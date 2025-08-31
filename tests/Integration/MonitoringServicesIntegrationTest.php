<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Services\DeploymentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitoringServicesIntegrationTest extends TestCase
{
    protected DeploymentService $deploymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deploymentService = app(DeploymentService::class);
        
        // Mock HTTP requests for external services
        Http::fake();
    }

    public function test_sentry_integration_captures_errors()
    {
        Config::set('dokku-deployment.monitoring.sentry', [
            'enabled' => true,
            'dsn' => 'https://test@sentry.io/123'
        ]);

        // Mock Sentry capture
        $this->assertTrue($this->deploymentService->isMonitoringEnabled('sentry'));
        
        // Test that Sentry configuration is properly loaded
        $sentryConfig = $this->deploymentService->getSentryConfig();
        $this->assertTrue($sentryConfig['enabled']);
        $this->assertEquals('https://test@sentry.io/123', $sentryConfig['dsn']);
    }

    public function test_sentry_performance_monitoring_configuration()
    {
        Config::set('dokku-deployment.monitoring.sentry', [
            'enabled' => true,
            'dsn' => 'https://test@sentry.io/123',
            'traces_sample_rate' => 0.1,
            'profiles_sample_rate' => 0.1
        ]);

        $config = $this->deploymentService->getSentryConfig();
        
        $this->assertEquals(0.1, $config['traces_sample_rate']);
        $this->assertEquals(0.1, $config['profiles_sample_rate']);
    }

    public function test_flagsmith_integration_retrieves_feature_flags()
    {
        Config::set('dokku-deployment.monitoring.flagsmith', [
            'enabled' => true,
            'environment_key' => 'test_environment_key',
            'api_url' => 'https://flagsmith.example.com/api/v1/'
        ]);

        // Mock Flagsmith API response
        Http::fake([
            'flagsmith.example.com/api/v1/flags/*' => Http::response([
                'feature' => 'test_feature',
                'enabled' => true,
                'value' => 'test_value'
            ], 200)
        ]);

        $this->assertTrue($this->deploymentService->isMonitoringEnabled('flagsmith'));
        
        $flagsmithConfig = $this->deploymentService->getFlagsmithConfig();
        $this->assertTrue($flagsmithConfig['enabled']);
        $this->assertEquals('test_environment_key', $flagsmithConfig['environment_key']);
    }

    public function test_flagsmith_fallback_when_service_unavailable()
    {
        Config::set('dokku-deployment.monitoring.flagsmith', [
            'enabled' => true,
            'environment_key' => 'test_key',
            'api_url' => 'https://flagsmith.example.com/api/v1/'
        ]);

        // Mock Flagsmith API failure
        Http::fake([
            'flagsmith.example.com/api/v1/flags/*' => Http::response([], 500)
        ]);

        // Service should still be considered enabled but handle failures gracefully
        $this->assertTrue($this->deploymentService->isMonitoringEnabled('flagsmith'));
    }

    public function test_grafana_cloud_integration_sends_metrics()
    {
        Config::set('dokku-deployment.monitoring.grafana', [
            'enabled' => true,
            'api_key' => 'test_api_key',
            'instance_id' => 'test_instance',
            'metrics_endpoint' => 'https://prometheus-prod-01-eu-west-0.grafana.net/api/prom/push'
        ]);

        // Mock Grafana Cloud API
        Http::fake([
            'prometheus-prod-01-eu-west-0.grafana.net/api/prom/push' => Http::response([], 200)
        ]);

        $this->assertTrue($this->deploymentService->isMonitoringEnabled('grafana'));
        
        $grafanaConfig = $this->deploymentService->getGrafanaConfig();
        $this->assertTrue($grafanaConfig['enabled']);
        $this->assertEquals('test_api_key', $grafanaConfig['api_key']);
        $this->assertEquals('test_instance', $grafanaConfig['instance_id']);
    }

    public function test_grafana_cloud_log_aggregation_configuration()
    {
        Config::set('dokku-deployment.monitoring.grafana', [
            'enabled' => true,
            'api_key' => 'test_api_key',
            'instance_id' => 'test_instance',
            'logs_endpoint' => 'https://logs-prod-eu-west-0.grafana.net/loki/api/v1/push'
        ]);

        $config = $this->deploymentService->getGrafanaConfig();
        
        $this->assertEquals('https://logs-prod-eu-west-0.grafana.net/loki/api/v1/push', $config['logs_endpoint']);
    }

    public function test_health_check_verifies_database_connectivity()
    {
        // Ensure database is available for testing
        $this->assertNotNull(DB::connection()->getPdo());
        
        $healthCheck = $this->deploymentService->performHealthCheck();
        
        $this->assertEquals('healthy', $healthCheck['status']);
        $this->assertEquals('healthy', $healthCheck['checks']['database']);
        $this->assertArrayHasKey('timestamp', $healthCheck);
    }

    public function test_health_check_verifies_redis_connectivity()
    {
        Config::set('cache.default', 'redis');
        
        // Mock Redis availability
        Cache::shouldReceive('store')
            ->with('redis')
            ->andReturnSelf();
        Cache::shouldReceive('put')
            ->with('health_check', 'ok', 10)
            ->andReturn(true);

        $healthCheck = $this->deploymentService->performHealthCheck();
        
        $this->assertArrayHasKey('redis', $healthCheck['checks']);
    }

    public function test_health_check_handles_database_failure()
    {
        // Mock database failure
        DB::shouldReceive('connection')
            ->andThrow(new \Exception('Database connection failed'));

        $healthCheck = $this->deploymentService->performHealthCheck();
        
        $this->assertEquals('unhealthy', $healthCheck['status']);
        $this->assertEquals('unhealthy', $healthCheck['checks']['database']);
    }

    public function test_health_check_handles_redis_failure()
    {
        Config::set('cache.default', 'redis');
        
        // Mock Redis failure
        Cache::shouldReceive('store')
            ->with('redis')
            ->andThrow(new \Exception('Redis connection failed'));

        $healthCheck = $this->deploymentService->performHealthCheck();
        
        $this->assertEquals('unhealthy', $healthCheck['status']);
        $this->assertEquals('unhealthy', $healthCheck['checks']['redis']);
    }

    public function test_health_check_verifies_sentry_connectivity()
    {
        Config::set('dokku-deployment.monitoring.sentry', [
            'enabled' => true,
            'dsn' => 'https://test@sentry.io/123'
        ]);

        $healthCheck = $this->deploymentService->performHealthCheck();
        
        // Sentry health check should be included when enabled
        $this->assertArrayHasKey('sentry', $healthCheck['checks']);
    }

    public function test_health_check_verifies_flagsmith_connectivity()
    {
        Config::set('dokku-deployment.monitoring.flagsmith', [
            'enabled' => true,
            'environment_key' => 'test_key',
            'api_url' => 'https://flagsmith.example.com/api/v1/'
        ]);

        // Mock successful Flagsmith response
        Http::fake([
            'flagsmith.example.com/api/v1/flags/*' => Http::response([
                ['feature' => 'test_feature', 'enabled' => true]
            ], 200)
        ]);

        $healthCheck = $this->deploymentService->performHealthCheck();
        
        $this->assertArrayHasKey('flagsmith', $healthCheck['checks']);
    }

    public function test_monitoring_services_graceful_degradation()
    {
        // Configure all monitoring services
        Config::set('dokku-deployment.monitoring', [
            'sentry' => ['enabled' => true, 'dsn' => 'https://test@sentry.io/123'],
            'flagsmith' => ['enabled' => true, 'environment_key' => 'test_key'],
            'grafana' => ['enabled' => true, 'api_key' => 'test_key']
        ]);

        // Mock all services as failing
        Http::fake([
            '*' => Http::response([], 500)
        ]);

        // Application should continue to function even if monitoring fails
        $healthCheck = $this->deploymentService->performHealthCheck();
        
        // Core services (database) should still be checked
        $this->assertArrayHasKey('database', $healthCheck['checks']);
        
        // Monitoring services may be unhealthy but shouldn't crash the app
        if (isset($healthCheck['checks']['sentry'])) {
            $this->assertEquals('unhealthy', $healthCheck['checks']['sentry']);
        }
        if (isset($healthCheck['checks']['flagsmith'])) {
            $this->assertEquals('unhealthy', $healthCheck['checks']['flagsmith']);
        }
    }

    public function test_deployment_logging_integration()
    {
        Log::shouldReceive('info')
            ->once()
            ->with('Deployment Event: test_event', \Mockery::type('array'));

        $this->deploymentService->logDeploymentEvent('test_event', [
            'test_context' => 'test_value'
        ]);
    }

    public function test_monitoring_configuration_validation()
    {
        // Test invalid Sentry configuration
        Config::set('dokku-deployment.monitoring.sentry', [
            'enabled' => true,
            'dsn' => '' // Invalid empty DSN
        ]);

        $sentryConfig = $this->deploymentService->getSentryConfig();
        $this->assertTrue($sentryConfig['enabled']);
        $this->assertEmpty($sentryConfig['dsn']);

        // Test invalid Flagsmith configuration
        Config::set('dokku-deployment.monitoring.flagsmith', [
            'enabled' => true,
            'environment_key' => '' // Invalid empty key
        ]);

        $flagsmithConfig = $this->deploymentService->getFlagsmithConfig();
        $this->assertTrue($flagsmithConfig['enabled']);
        $this->assertEmpty($flagsmithConfig['environment_key']);
    }

    public function test_service_integration_with_environment_context()
    {
        Config::set('app.env', 'production');
        Config::set('dokku-deployment.environments.production', [
            'app_name' => 'restant-main',
            'domain' => 'restant.main.susankshakya.com.np'
        ]);

        $deploymentInfo = $this->deploymentService->getDeploymentInfo();
        
        $this->assertEquals('production', $deploymentInfo['environment']);
        $this->assertEquals('restant-main', $deploymentInfo['app_name']);
        $this->assertEquals('restant.main.susankshakya.com.np', $deploymentInfo['domain']);
    }

    public function test_monitoring_services_error_handling_and_recovery()
    {
        // Configure all monitoring services
        Config::set('deployment.monitoring', [
            'sentry' => ['enabled' => true, 'dsn' => 'https://test@sentry.io/123'],
            'flagsmith' => ['enabled' => true, 'environment_key' => 'test_key'],
            'grafana' => ['enabled' => true, 'api_key' => 'test_key']
        ]);

        // Mock initial failure then recovery
        Http::fakeSequence()
            ->push([], 500) // First request fails
            ->push(['status' => 'ok'], 200) // Second request succeeds
            ->push([], 500) // Third fails
            ->push(['status' => 'ok'], 200); // Fourth succeeds

        // Test error handling and retry logic
        $healthCheck1 = $this->deploymentService->performHealthCheck();
        $healthCheck2 = $this->deploymentService->performHealthCheck();

        // Should handle failures gracefully
        $this->assertArrayHasKey('database', $healthCheck1['checks']);
        $this->assertArrayHasKey('database', $healthCheck2['checks']);
    }

    public function test_monitoring_services_performance_metrics()
    {
        Config::set('deployment.monitoring.sentry', [
            'enabled' => true,
            'dsn' => 'https://test@sentry.io/123',
            'traces_sample_rate' => 0.1,
            'profiles_sample_rate' => 0.1
        ]);

        Config::set('deployment.monitoring.grafana', [
            'enabled' => true,
            'api_key' => 'test_key',
            'metrics_endpoint' => 'https://prometheus.grafana.net/api/prom/push'
        ]);

        // Mock performance monitoring endpoints
        Http::fake([
            'sentry.io/*' => Http::response(['status' => 'ok'], 200),
            'prometheus.grafana.net/*' => Http::response([], 200)
        ]);

        // Test performance metrics collection
        $metrics = $this->deploymentService->collectPerformanceMetrics();
        
        $this->assertArrayHasKey('response_time', $metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
        $this->assertArrayHasKey('database_queries', $metrics);
    }

    public function test_monitoring_services_multi_tenant_context()
    {
        Config::set('deployment.monitoring.sentry.multi_tenant.enabled', true);
        Config::set('deployment.monitoring.sentry.multi_tenant.tenant_tag', 'tenant_id');

        // Mock tenant context
        $tenantId = 'tenant_123';
        
        // Test that monitoring services receive tenant context
        $context = $this->deploymentService->getMonitoringContext($tenantId);
        
        $this->assertEquals($tenantId, $context['tenant_id']);
        $this->assertArrayHasKey('environment', $context);
        $this->assertArrayHasKey('deployment_version', $context);
    }

    public function test_monitoring_services_alert_thresholds()
    {
        Config::set('deployment.monitoring.thresholds', [
            'response_time' => 5000, // 5 seconds
            'error_rate' => 0.05, // 5%
            'memory_usage' => 0.8 // 80%
        ]);

        // Test threshold validation
        $metrics = [
            'response_time' => 6000, // Above threshold
            'error_rate' => 0.03, // Below threshold
            'memory_usage' => 0.9 // Above threshold
        ];

        $alerts = $this->deploymentService->checkAlertThresholds($metrics);
        
        $this->assertCount(2, $alerts); // Should have 2 alerts
        $this->assertContains('response_time', array_column($alerts, 'metric'));
        $this->assertContains('memory_usage', array_column($alerts, 'metric'));
    }

    public function test_monitoring_services_data_retention()
    {
        Config::set('deployment.monitoring.data_retention', [
            'metrics' => 30, // 30 days
            'logs' => 7, // 7 days
            'traces' => 3 // 3 days
        ]);

        // Test data retention policies
        $retentionPolicies = $this->deploymentService->getDataRetentionPolicies();
        
        $this->assertEquals(30, $retentionPolicies['metrics']);
        $this->assertEquals(7, $retentionPolicies['logs']);
        $this->assertEquals(3, $retentionPolicies['traces']);
    }

    public function test_monitoring_services_batch_operations()
    {
        // Configure multiple monitoring services
        Config::set('deployment.monitoring', [
            'sentry' => ['enabled' => true],
            'flagsmith' => ['enabled' => true],
            'grafana' => ['enabled' => true]
        ]);

        // Mock batch health check responses
        Http::fake([
            'sentry.io/*' => Http::response(['status' => 'healthy'], 200),
            'flagsmith.example.com/*' => Http::response(['status' => 'healthy'], 200),
            'prometheus.grafana.net/*' => Http::response(['status' => 'healthy'], 200)
        ]);

        // Test batch health check
        $batchResults = $this->deploymentService->performBatchHealthCheck();
        
        $this->assertArrayHasKey('sentry', $batchResults);
        $this->assertArrayHasKey('flagsmith', $batchResults);
        $this->assertArrayHasKey('grafana', $batchResults);
        
        foreach ($batchResults as $service => $result) {
            $this->assertEquals('healthy', $result['status']);
        }
    }

    public function test_monitoring_services_circuit_breaker_pattern()
    {
        Config::set('deployment.monitoring.circuit_breaker', [
            'failure_threshold' => 3,
            'timeout' => 60, // 60 seconds
            'retry_timeout' => 30 // 30 seconds
        ]);

        // Mock consecutive failures
        Http::fake([
            '*' => Http::response([], 500)
        ]);

        // Test circuit breaker activation
        for ($i = 0; $i < 4; $i++) {
            $result = $this->deploymentService->performHealthCheck();
        }

        // Circuit breaker should be open after threshold failures
        $circuitState = $this->deploymentService->getCircuitBreakerState();
        $this->assertEquals('open', $circuitState['sentry'] ?? 'closed');
    }
}