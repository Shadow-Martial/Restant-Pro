<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DeploymentService;
use Illuminate\Support\Facades\Config;

class DeploymentConfigurationTest extends TestCase
{
    protected DeploymentService $deploymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deploymentService = app(DeploymentService::class);
    }

    public function test_gets_current_environment()
    {
        Config::set('app.env', 'production');
        $this->assertEquals('production', $this->deploymentService->getCurrentEnvironment());

        Config::set('app.env', 'staging');
        $this->assertEquals('staging', $this->deploymentService->getCurrentEnvironment());
    }

    public function test_gets_environment_config()
    {
        Config::set('app.env', 'production');
        Config::set('dokku-deployment.environments.production', [
            'app_name' => 'restant-main',
            'domain' => 'restant.main.susankshakya.com.np',
            'ssl_enabled' => true
        ]);

        $config = $this->deploymentService->getEnvironmentConfig();
        
        $this->assertEquals('restant-main', $config['app_name']);
        $this->assertEquals('restant.main.susankshakya.com.np', $config['domain']);
        $this->assertTrue($config['ssl_enabled']);
    }

    public function test_detects_dokku_environment()
    {
        // Test with DOKKU_APP_NAME
        putenv('DOKKU_APP_NAME=test-app');
        $this->assertTrue($this->deploymentService->isDokkuEnvironment());
        putenv('DOKKU_APP_NAME=');

        // Test with DATABASE_URL
        putenv('DATABASE_URL=postgres://user:pass@host:5432/db');
        $this->assertTrue($this->deploymentService->isDokkuEnvironment());
        putenv('DATABASE_URL=');

        // Test without Dokku indicators
        $this->assertFalse($this->deploymentService->isDokkuEnvironment());
    }

    public function test_gets_app_name()
    {
        // Test with DOKKU_APP_NAME
        putenv('DOKKU_APP_NAME=test-app');
        $this->assertEquals('test-app', $this->deploymentService->getAppName());
        putenv('DOKKU_APP_NAME=');

        // Test with config fallback
        Config::set('dokku-deployment.environments.production.app_name', 'config-app');
        Config::set('app.env', 'production');
        $this->assertEquals('config-app', $this->deploymentService->getAppName());
    }

    public function test_gets_domain()
    {
        Config::set('app.env', 'production');
        Config::set('dokku-deployment.environments.production.domain', 'test.example.com');
        
        $this->assertEquals('test.example.com', $this->deploymentService->getDomain());
    }

    public function test_checks_ssl_enabled()
    {
        Config::set('app.env', 'production');
        Config::set('dokku-deployment.environments.production.ssl_enabled', true);
        
        $this->assertTrue($this->deploymentService->isSslEnabled());

        Config::set('dokku-deployment.environments.production.ssl_enabled', false);
        $this->assertFalse($this->deploymentService->isSslEnabled());
    }

    public function test_gets_monitoring_config()
    {
        Config::set('dokku-deployment.monitoring.sentry', [
            'enabled' => true,
            'dsn' => 'https://test@sentry.io/123'
        ]);

        $config = $this->deploymentService->getMonitoringConfig('sentry');
        
        $this->assertTrue($config['enabled']);
        $this->assertEquals('https://test@sentry.io/123', $config['dsn']);
    }

    public function test_checks_monitoring_enabled()
    {
        Config::set('dokku-deployment.monitoring.sentry.enabled', true);
        $this->assertTrue($this->deploymentService->isMonitoringEnabled('sentry'));

        Config::set('dokku-deployment.monitoring.sentry.enabled', false);
        $this->assertFalse($this->deploymentService->isMonitoringEnabled('sentry'));
    }

    public function test_gets_sentry_config()
    {
        Config::set('dokku-deployment.monitoring.sentry', [
            'enabled' => true,
            'dsn' => 'https://test@sentry.io/123',
            'traces_sample_rate' => 0.1
        ]);

        $config = $this->deploymentService->getSentryConfig();
        
        $this->assertTrue($config['enabled']);
        $this->assertEquals('https://test@sentry.io/123', $config['dsn']);
        $this->assertEquals(0.1, $config['traces_sample_rate']);
    }

    public function test_gets_flagsmith_config()
    {
        Config::set('dokku-deployment.monitoring.flagsmith', [
            'enabled' => true,
            'environment_key' => 'test_key',
            'api_url' => 'https://flagsmith.example.com/api/v1/'
        ]);

        $config = $this->deploymentService->getFlagsmithConfig();
        
        $this->assertTrue($config['enabled']);
        $this->assertEquals('test_key', $config['environment_key']);
        $this->assertEquals('https://flagsmith.example.com/api/v1/', $config['api_url']);
    }

    public function test_gets_grafana_config()
    {
        Config::set('dokku-deployment.monitoring.grafana', [
            'enabled' => true,
            'api_key' => 'test_api_key',
            'instance_id' => 'test_instance'
        ]);

        $config = $this->deploymentService->getGrafanaConfig();
        
        $this->assertTrue($config['enabled']);
        $this->assertEquals('test_api_key', $config['api_key']);
        $this->assertEquals('test_instance', $config['instance_id']);
    }

    public function test_gets_service_config()
    {
        Config::set('dokku-deployment.services.mysql', [
            'enabled' => true,
            'version' => '8.0'
        ]);

        $config = $this->deploymentService->getServiceConfig('mysql');
        
        $this->assertTrue($config['enabled']);
        $this->assertEquals('8.0', $config['version']);
    }

    public function test_gets_deployment_config()
    {
        Config::set('dokku-deployment.deployment', [
            'php_version' => '8.1',
            'node_version' => '18',
            'health_check_timeout' => 30
        ]);

        $config = $this->deploymentService->getDeploymentConfig();
        
        $this->assertEquals('8.1', $config['php_version']);
        $this->assertEquals('18', $config['node_version']);
        $this->assertEquals(30, $config['health_check_timeout']);
    }

    public function test_gets_health_check_config()
    {
        Config::set('dokku-deployment.deployment', [
            'health_check_timeout' => 45,
            'health_check_retries' => 5
        ]);

        $config = $this->deploymentService->getHealthCheckConfig();
        
        $this->assertEquals(45, $config['timeout']);
        $this->assertEquals(5, $config['retries']);
    }

    public function test_gets_deployment_info()
    {
        Config::set('app.env', 'production');
        Config::set('dokku-deployment.environments.production', [
            'app_name' => 'test-app',
            'domain' => 'test.example.com',
            'ssl_enabled' => true
        ]);
        Config::set('dokku-deployment.monitoring.sentry.enabled', true);
        Config::set('dokku-deployment.monitoring.flagsmith.enabled', false);
        Config::set('dokku-deployment.monitoring.grafana.enabled', true);

        putenv('DEPLOYMENT_TIME=2024-01-01T12:00:00Z');
        putenv('GIT_COMMIT=abc123def456');

        $info = $this->deploymentService->getDeploymentInfo();
        
        $this->assertEquals('production', $info['environment']);
        $this->assertEquals('test-app', $info['app_name']);
        $this->assertEquals('test.example.com', $info['domain']);
        $this->assertTrue($info['ssl_enabled']);
        $this->assertTrue($info['monitoring']['sentry']);
        $this->assertFalse($info['monitoring']['flagsmith']);
        $this->assertTrue($info['monitoring']['grafana']);
        $this->assertEquals('2024-01-01T12:00:00Z', $info['deployment_time']);
        $this->assertEquals('abc123def456', $info['git_commit']);
    }

    public function test_validates_deployment_configuration()
    {
        // Test valid configuration
        Config::set('deployment.environments.production', [
            'subdomain' => 'main',
            'branch' => 'main',
            'dokku_app' => 'restant-main'
        ]);

        $isValid = $this->deploymentService->validateConfiguration('production');
        $this->assertTrue($isValid);

        // Test invalid configuration - missing required fields
        Config::set('deployment.environments.staging', [
            'subdomain' => 'staging'
            // Missing branch and dokku_app
        ]);

        $isValid = $this->deploymentService->validateConfiguration('staging');
        $this->assertFalse($isValid);
    }

    public function test_validates_monitoring_configuration()
    {
        // Test valid Sentry configuration
        Config::set('deployment.monitoring.sentry', [
            'enabled' => true,
            'traces_sample_rate' => 0.1
        ]);

        $isValid = $this->deploymentService->validateMonitoringConfig('sentry');
        $this->assertTrue($isValid);

        // Test invalid Flagsmith configuration
        Config::set('deployment.monitoring.flagsmith', [
            'enabled' => true
            // Missing environment_key
        ]);

        $isValid = $this->deploymentService->validateMonitoringConfig('flagsmith');
        $this->assertFalse($isValid);
    }

    public function test_validates_notification_configuration()
    {
        // Test valid notification configuration
        Config::set('deployment.notifications.channels.slack', [
            'enabled' => true,
            'webhook_url' => 'https://hooks.slack.com/test'
        ]);

        $isValid = $this->deploymentService->validateNotificationConfig('slack');
        $this->assertTrue($isValid);

        // Test invalid email configuration
        Config::set('deployment.notifications.channels.email', [
            'enabled' => true
            // Missing recipients
        ]);

        $isValid = $this->deploymentService->validateNotificationConfig('email');
        $this->assertFalse($isValid);
    }

    public function test_validates_rollback_configuration()
    {
        // Test valid rollback configuration
        Config::set('deployment.rollback', [
            'enabled' => true,
            'auto_rollback_on_failure' => true,
            'max_rollback_attempts' => 3
        ]);

        $isValid = $this->deploymentService->validateRollbackConfig();
        $this->assertTrue($isValid);

        // Test invalid rollback configuration
        Config::set('deployment.rollback', [
            'enabled' => true,
            'max_rollback_attempts' => -1 // Invalid negative value
        ]);

        $isValid = $this->deploymentService->validateRollbackConfig();
        $this->assertFalse($isValid);
    }

    public function test_validates_health_check_configuration()
    {
        // Test valid health check configuration
        Config::set('deployment.health_checks', [
            'enabled' => true,
            'timeout' => 30,
            'retries' => 3,
            'endpoints' => [
                'app' => '/health'
            ]
        ]);

        $isValid = $this->deploymentService->validateHealthCheckConfig();
        $this->assertTrue($isValid);

        // Test invalid health check configuration
        Config::set('deployment.health_checks', [
            'enabled' => true,
            'timeout' => 0, // Invalid timeout
            'retries' => -1 // Invalid retries
        ]);

        $isValid = $this->deploymentService->validateHealthCheckConfig();
        $this->assertFalse($isValid);
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('DOKKU_APP_NAME=');
        putenv('DATABASE_URL=');
        putenv('DEPLOYMENT_TIME=');
        putenv('GIT_COMMIT=');

        parent::tearDown();
    }
}