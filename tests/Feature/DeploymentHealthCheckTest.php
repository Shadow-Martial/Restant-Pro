<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class DeploymentHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_overall_status()
    {
        $response = $this->get('/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'deployment_environment',
            'version',
            'services' => [
                'database',
                'cache',
                'sentry',
                'flagsmith',
                'grafana',
            ],
            'ssl',
        ]);
    }

    public function test_database_health_check_endpoint()
    {
        $response = $this->get('/health/database');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'service',
            'status',
            'timestamp',
            'checks' => [
                'connection',
                'query',
                'migrations',
            ],
        ]);

        $data = $response->json();
        $this->assertEquals('database', $data['service']);
        $this->assertEquals('healthy', $data['status']);
    }

    public function test_database_health_check_with_connection_failure()
    {
        // Temporarily break the database connection
        Config::set('database.connections.testing.database', 'nonexistent_database');
        DB::purge('testing');

        $response = $this->get('/health/database');

        $response->assertStatus(503);
        $data = $response->json();
        $this->assertEquals('unhealthy', $data['status']);
        $this->assertEquals('unhealthy', $data['checks']['connection']['status']);
    }

    public function test_cache_health_check_functionality()
    {
        // Test cache operations
        $testKey = 'health_test_' . time();
        $testValue = 'test_value_' . uniqid();

        Cache::put($testKey, $testValue, 10);
        $retrievedValue = Cache::get($testKey);
        Cache::forget($testKey);

        $this->assertEquals($testValue, $retrievedValue);
    }

    public function test_sentry_health_check_endpoint()
    {
        $response = $this->get('/health/sentry');

        $response->assertJsonStructure([
            'service',
            'status',
            'timestamp',
            'checks',
        ]);

        $data = $response->json();
        $this->assertEquals('sentry', $data['service']);
    }

    public function test_flagsmith_health_check_endpoint()
    {
        $response = $this->get('/health/flagsmith');

        $response->assertJsonStructure([
            'service',
            'status',
            'timestamp',
            'checks',
        ]);

        $data = $response->json();
        $this->assertEquals('flagsmith', $data['service']);
    }

    public function test_grafana_health_check_endpoint()
    {
        $response = $this->get('/health/grafana');

        $response->assertJsonStructure([
            'service',
            'status',
            'timestamp',
            'checks',
        ]);

        $data = $response->json();
        $this->assertEquals('grafana', $data['service']);
    }

    public function test_ssl_health_check_endpoint()
    {
        $response = $this->get('/health/ssl');

        $response->assertJsonStructure([
            'service',
            'status',
            'timestamp',
            'certificate',
        ]);

        $data = $response->json();
        $this->assertEquals('ssl', $data['service']);
    }

    public function test_health_check_with_degraded_services()
    {
        // Mock a scenario where optional services are down but critical services are up
        Config::set('flagsmith.enabled', false);
        Config::set('monitoring.grafana.enabled', false);

        $response = $this->get('/health');

        // Should still return 200 since critical services are healthy
        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertContains($data['status'], ['healthy', 'degraded']);
    }

    public function test_deployment_health_command_success()
    {
        $this->artisan('deployment:health-check')
            ->expectsOutput('🔍 Starting deployment health verification...')
            ->expectsOutput('🎉 Deployment health verification passed!')
            ->assertExitCode(0);
    }

    public function test_deployment_health_command_with_json_output()
    {
        $this->artisan('deployment:health-check', ['--format' => 'json'])
            ->assertExitCode(0);
    }

    public function test_deployment_health_command_critical_only()
    {
        $this->artisan('deployment:health-check', ['--critical-only' => true])
            ->expectsOutput('🔍 Starting deployment health verification...')
            ->assertExitCode(0);
    }

    public function test_deployment_health_command_with_timeout()
    {
        $this->artisan('deployment:health-check', ['--timeout' => 10])
            ->expectsOutput('🔍 Starting deployment health verification...')
            ->assertExitCode(0);
    }

    public function test_health_check_includes_deployment_context()
    {
        $response = $this->get('/health');

        $data = $response->json();
        $this->assertArrayHasKey('deployment_environment', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertEquals(config('app.env'), $data['deployment_environment']);
    }

    public function test_health_check_performance_tracking()
    {
        $start = microtime(true);
        
        $response = $this->get('/health');
        
        $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds
        
        $response->assertStatus(200);
        
        // Health check should complete within reasonable time (< 5 seconds)
        $this->assertLessThan(5000, $duration, 'Health check took too long');
    }
}