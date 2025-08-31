<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\GrafanaCloudService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class GrafanaCloudIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock Grafana Cloud configuration for testing
        Config::set('monitoring.grafana.enabled', true);
        Config::set('monitoring.grafana.api_key', 'test-api-key');
        Config::set('monitoring.grafana.instance_id', 'test-instance-id');
    }

    public function test_grafana_service_can_be_instantiated()
    {
        $service = app(GrafanaCloudService::class);
        $this->assertInstanceOf(GrafanaCloudService::class, $service);
    }

    public function test_can_send_metric_to_grafana_cloud()
    {
        Http::fake([
            'prometheus-prod-01-eu-west-0.grafana.net/*' => Http::response([], 200)
        ]);

        $service = app(GrafanaCloudService::class);
        $result = $service->sendMetric('test_metric', 1.0, ['test' => 'label']);

        $this->assertTrue($result);
    }

    public function test_can_send_performance_metrics()
    {
        Http::fake([
            'prometheus-prod-01-eu-west-0.grafana.net/*' => Http::response([], 200)
        ]);

        $service = app(GrafanaCloudService::class);
        
        $metrics = [
            [
                'name' => 'test_performance_metric',
                'value' => 100.5,
                'labels' => ['type' => 'performance']
            ]
        ];

        $result = $service->sendPerformanceMetrics($metrics);
        $this->assertTrue($result);
    }

    public function test_can_send_logs_to_grafana_cloud()
    {
        Http::fake([
            'logs-prod-eu-west-0.grafana.net/*' => Http::response([], 200)
        ]);

        $service = app(GrafanaCloudService::class);
        
        $logs = [
            [
                'timestamp' => now()->timestamp,
                'level' => 'info',
                'message' => 'Test log message',
                'labels' => ['source' => 'test']
            ]
        ];

        $result = $service->sendLogs($logs);
        $this->assertTrue($result);
    }

    public function test_health_check_endpoint_returns_grafana_status()
    {
        Http::fake([
            'prometheus-prod-01-eu-west-0.grafana.net/*' => Http::response([], 200)
        ]);

        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'timestamp',
                    'checks' => [
                        'grafana' => [
                            'status',
                            'message'
                        ]
                    ]
                ]);
    }

    public function test_grafana_performance_middleware_tracks_requests()
    {
        Http::fake([
            'prometheus-prod-01-eu-west-0.grafana.net/*' => Http::response([], 200)
        ]);

        // Make a request that will be tracked by the middleware
        $response = $this->get('/api/health');

        $response->assertStatus(200);
        
        // Verify that HTTP requests were made to Grafana (metrics were sent)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'prometheus-prod-01-eu-west-0.grafana.net');
        });
    }

    public function test_infrastructure_metrics_collection_command()
    {
        Http::fake([
            'prometheus-prod-01-eu-west-0.grafana.net/*' => Http::response([], 200)
        ]);

        $this->artisan('grafana:collect-metrics')
             ->expectsOutput('Collecting infrastructure metrics...')
             ->assertExitCode(0);
    }

    public function test_grafana_service_handles_disabled_state()
    {
        Config::set('monitoring.grafana.enabled', false);

        $service = app(GrafanaCloudService::class);
        $result = $service->sendMetric('test_metric', 1.0);

        $this->assertFalse($result);
    }

    public function test_grafana_service_handles_missing_credentials()
    {
        Config::set('monitoring.grafana.api_key', null);

        $service = app(GrafanaCloudService::class);
        $result = $service->sendMetric('test_metric', 1.0);

        $this->assertFalse($result);
    }

    public function test_grafana_service_handles_api_failures_gracefully()
    {
        Http::fake([
            'prometheus-prod-01-eu-west-0.grafana.net/*' => Http::response([], 500)
        ]);

        $service = app(GrafanaCloudService::class);
        $result = $service->sendMetric('test_metric', 1.0);

        $this->assertFalse($result);
    }
}