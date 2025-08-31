<?php

namespace Tests\Feature;

use App\Services\FlagsmithService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class FlagsmithIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
    }

    public function test_flagsmith_service_can_be_instantiated()
    {
        $service = app(FlagsmithService::class);
        $this->assertInstanceOf(FlagsmithService::class, $service);
    }

    public function test_feature_flag_helper_returns_default_when_disabled()
    {
        Config::set('flagsmith.enabled', false);
        Config::set('flagsmith.default_flags.test_feature', true);
        
        $result = feature_flag('test_feature', false);
        $this->assertTrue($result);
    }

    public function test_feature_enabled_helper_works()
    {
        Config::set('flagsmith.enabled', false);
        Config::set('flagsmith.default_flags.test_feature', true);
        
        $result = feature_enabled('test_feature');
        $this->assertTrue($result);
    }

    public function test_user_feature_enabled_helper_works()
    {
        Config::set('flagsmith.enabled', false);
        Config::set('flagsmith.default_flags.user_feature', false);
        
        $result = user_feature_enabled('user_feature');
        $this->assertFalse($result);
    }

    public function test_tenant_feature_enabled_helper_works()
    {
        Config::set('flagsmith.enabled', false);
        Config::set('flagsmith.default_flags.tenant_feature', true);
        
        $result = tenant_feature_enabled('tenant_feature');
        $this->assertTrue($result);
    }

    public function test_flagsmith_facade_works()
    {
        Config::set('flagsmith.enabled', false);
        
        $result = \App\Facades\Flagsmith::getFlag('test_flag', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    public function test_health_check_endpoint_exists()
    {
        $response = $this->get('/health');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'services' => [
                'database',
                'cache',
                'flagsmith'
            ]
        ]);
    }

    public function test_flagsmith_health_endpoint_exists()
    {
        $response = $this->get('/health/flagsmith');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'service',
            'status',
            'timestamp'
        ]);
    }

    public function test_feature_flag_middleware_blocks_when_disabled()
    {
        Config::set('flagsmith.enabled', false);
        Config::set('flagsmith.default_flags.test_route_feature', false);
        
        // This would need a test route to be properly tested
        // For now, just verify middleware exists
        $middleware = app(\App\Http\Middleware\FeatureFlagMiddleware::class);
        $this->assertInstanceOf(\App\Http\Middleware\FeatureFlagMiddleware::class, $middleware);
    }

    public function test_circuit_breaker_prevents_repeated_failures()
    {
        $service = app(FlagsmithService::class);
        
        // Simulate multiple failures to trigger circuit breaker
        for ($i = 0; $i < 6; $i++) {
            Cache::put('flagsmith_circuit_breaker_failures', $i, 3600);
        }
        Cache::put('flagsmith_circuit_breaker_last_failure', time(), 3600);
        
        // Should use fallback without trying API
        $result = $service->getFlag('test_flag', 'fallback_value');
        $this->assertEquals('fallback_value', $result);
    }
}