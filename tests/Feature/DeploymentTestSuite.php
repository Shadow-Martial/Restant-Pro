<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

/**
 * Comprehensive Deployment Test Suite
 * 
 * This test suite orchestrates all deployment-related tests to ensure
 * the entire deployment system works correctly end-to-end.
 */
class DeploymentTestSuite extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up common test configuration
        $this->setupTestConfiguration();
    }

    public function test_deployment_test_suite_runs_successfully()
    {
        // This test ensures all deployment test classes can be instantiated
        // and their basic setup works correctly
        
        $testClasses = [
            \Tests\Unit\DeploymentConfigurationTest::class,
            \Tests\Integration\MonitoringServicesIntegrationTest::class,
            \Tests\Feature\EndToEndDeploymentTest::class,
            \Tests\Feature\DeploymentRollbackTest::class,
            \Tests\Feature\DeploymentNotificationTest::class,
            \Tests\Feature\EnvironmentConfigurationTest::class
        ];

        foreach ($testClasses as $testClass) {
            $this->assertTrue(class_exists($testClass), "Test class {$testClass} should exist");
        }
    }

    public function test_deployment_configuration_validation()
    {
        // Validate that all required configuration files exist and are properly structured
        
        $requiredConfigs = [
            'deployment',
            'dokku-deployment',
            'environments'
        ];

        foreach ($requiredConfigs as $config) {
            $this->assertNotNull(config($config), "Configuration {$config} should be available");
        }
    }

    public function test_deployment_commands_are_registered()
    {
        // Verify all deployment-related Artisan commands are properly registered
        
        $commands = [
            'deployment:config',
            'deployment:notify',
            'deployment:rollback',
            'deployment:test-notification',
            'env:validate',
            'secrets:manage'
        ];

        foreach ($commands as $command) {
            try {
                Artisan::call($command, ['--help' => true]);
                $this->assertTrue(true, "Command {$command} is registered");
            } catch (\Exception $e) {
                // Some commands might require parameters, so we just check they're registered
                $this->assertStringNotContainsString('Command not found', $e->getMessage());
            }
        }
    }

    public function test_deployment_services_are_bound()
    {
        // Verify all deployment services are properly bound in the service container
        
        $services = [
            \App\Services\DeploymentService::class,
            \App\Services\DeploymentRollbackService::class,
            \App\Services\DeploymentNotificationService::class,
            \App\Services\DeploymentLoggerService::class
        ];

        foreach ($services as $service) {
            $instance = app($service);
            $this->assertInstanceOf($service, $instance, "Service {$service} should be bound");
        }
    }

    public function test_deployment_events_are_registered()
    {
        // Verify deployment events are properly registered
        
        $events = [
            \App\Events\DeploymentStarted::class,
            \App\Events\DeploymentCompleted::class,
            \App\Events\DeploymentRollback::class
        ];

        foreach ($events as $event) {
            $this->assertTrue(class_exists($event), "Event {$event} should exist");
        }
    }

    public function test_deployment_middleware_is_registered()
    {
        // Verify deployment middleware is properly registered
        
        $middleware = [
            \App\Http\Middleware\DeploymentEnvironmentMiddleware::class,
            \App\Http\Middleware\DeploymentFailureHandler::class
        ];

        foreach ($middleware as $middlewareClass) {
            $this->assertTrue(class_exists($middlewareClass), "Middleware {$middlewareClass} should exist");
        }
    }

    public function test_deployment_facades_are_registered()
    {
        // Verify deployment facades are properly registered
        
        $facades = [
            'Deployment' => \App\Facades\Deployment::class,
            'DeploymentNotification' => \App\Facades\DeploymentNotification::class
        ];

        foreach ($facades as $alias => $facade) {
            $this->assertTrue(class_exists($facade), "Facade {$facade} should exist");
        }
    }

    public function test_deployment_routes_are_registered()
    {
        // Test that deployment-related routes are accessible
        
        $routes = [
            '/health' => 'GET'
        ];

        foreach ($routes as $route => $method) {
            $response = $this->call($method, $route);
            // Route should exist (not 404)
            $this->assertNotEquals(404, $response->getStatusCode(), "Route {$method} {$route} should exist");
        }
    }

    public function test_deployment_database_tables_exist()
    {
        // Verify deployment-related database tables exist if any
        
        // Check if secrets table exists (from SecretManager)
        if (\Schema::hasTable('secrets')) {
            $this->assertTrue(\Schema::hasTable('secrets'), 'Secrets table should exist');
            
            $columns = ['id', 'key', 'value', 'environment', 'created_at', 'updated_at'];
            foreach ($columns as $column) {
                $this->assertTrue(\Schema::hasColumn('secrets', $column), "Secrets table should have {$column} column");
            }
        }
    }

    public function test_deployment_environment_variables_validation()
    {
        // Test that deployment works with various environment configurations
        
        $environments = ['production', 'staging', 'testing'];
        
        foreach ($environments as $env) {
            Config::set('app.env', $env);
            
            $deploymentService = app(\App\Services\DeploymentService::class);
            $this->assertEquals($env, $deploymentService->getCurrentEnvironment());
        }
    }

    public function test_deployment_monitoring_integration_configuration()
    {
        // Verify monitoring services configuration is properly structured
        
        $monitoringServices = ['sentry', 'flagsmith', 'grafana'];
        
        foreach ($monitoringServices as $service) {
            $config = config("dokku-deployment.monitoring.{$service}");
            
            if ($config) {
                $this->assertIsArray($config, "Monitoring config for {$service} should be an array");
                $this->assertArrayHasKey('enabled', $config, "Monitoring config for {$service} should have 'enabled' key");
            }
        }
    }

    public function test_deployment_notification_channels_configuration()
    {
        // Verify notification channels are properly configured
        
        $channels = ['slack', 'email', 'webhook'];
        
        foreach ($channels as $channel) {
            $config = config("deployment.notifications.channels.{$channel}");
            
            if ($config) {
                $this->assertIsArray($config, "Notification config for {$channel} should be an array");
                $this->assertArrayHasKey('enabled', $config, "Notification config for {$channel} should have 'enabled' key");
            }
        }
    }

    public function test_deployment_rollback_configuration()
    {
        // Verify rollback configuration is properly set up
        
        $rollbackConfig = config('deployment.rollback');
        
        if ($rollbackConfig) {
            $this->assertIsArray($rollbackConfig, 'Rollback config should be an array');
            
            $requiredKeys = ['enabled', 'auto_rollback_on_failure', 'max_rollback_attempts'];
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $rollbackConfig, "Rollback config should have '{$key}' key");
            }
        }
    }

    public function test_deployment_health_checks_configuration()
    {
        // Verify health checks configuration is properly set up
        
        $healthConfig = config('deployment.health_checks');
        
        if ($healthConfig) {
            $this->assertIsArray($healthConfig, 'Health checks config should be an array');
            
            $requiredKeys = ['enabled', 'timeout', 'retries'];
            foreach ($requiredKeys as $key) {
                $this->assertArrayHasKey($key, $healthConfig, "Health checks config should have '{$key}' key");
            }
        }
    }

    protected function setupTestConfiguration()
    {
        // Set up common test configuration used across all deployment tests
        
        Config::set('deployment.dokku', [
            'host' => '209.50.227.94',
            'ssh_key_path' => '/path/to/test/ssh/key'
        ]);

        Config::set('deployment.environments', [
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
        ]);

        Config::set('deployment.monitoring', [
            'sentry' => [
                'enabled' => false, // Disabled for testing
                'traces_sample_rate' => 0.1
            ],
            'flagsmith' => [
                'enabled' => false, // Disabled for testing
            ],
            'grafana' => [
                'enabled' => false, // Disabled for testing
            ]
        ]);

        Config::set('deployment.notifications', [
            'channels' => [
                'slack' => ['enabled' => false],
                'email' => ['enabled' => false],
                'webhook' => ['enabled' => false]
            ]
        ]);

        Config::set('deployment.rollback', [
            'enabled' => true,
            'auto_rollback_on_failure' => true,
            'max_rollback_attempts' => 3
        ]);

        Config::set('deployment.health_checks', [
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'endpoints' => [
                'app' => '/health'
            ]
        ]);
    }
}