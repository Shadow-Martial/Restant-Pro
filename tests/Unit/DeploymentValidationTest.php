<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DeploymentService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Validator;

class DeploymentValidationTest extends TestCase
{
    protected DeploymentService $deploymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deploymentService = app(DeploymentService::class);
    }

    public function test_validates_environment_variables()
    {
        // Test with valid environment variables
        putenv('APP_KEY=base64:' . base64_encode(str_repeat('a', 32)));
        putenv('DB_DATABASE=test_db');
        putenv('DB_USERNAME=test_user');
        putenv('DB_PASSWORD=test_password');

        $validation = $this->deploymentService->validateEnvironmentVariables();
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['missing']);

        // Test with missing environment variables
        putenv('APP_KEY=');
        putenv('DB_DATABASE=');

        $validation = $this->deploymentService->validateEnvironmentVariables();
        $this->assertFalse($validation['valid']);
        $this->assertContains('APP_KEY', $validation['missing']);
        $this->assertContains('DB_DATABASE', $validation['missing']);
    }

    public function test_validates_dokku_configuration()
    {
        // Test valid Dokku configuration
        Config::set('deployment.dokku', [
            'host' => '209.50.227.94',
            'ssh_key_path' => '/path/to/ssh/key'
        ]);

        $validation = $this->deploymentService->validateDokkuConfig();
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);

        // Test invalid Dokku configuration
        Config::set('deployment.dokku', [
            'host' => '', // Empty host
            'ssh_key_path' => '/nonexistent/path'
        ]);

        $validation = $this->deploymentService->validateDokkuConfig();
        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    public function test_validates_subdomain_format()
    {
        // Test valid subdomain formats
        $validSubdomains = ['main', 'staging', 'feature-branch', 'test123'];
        
        foreach ($validSubdomains as $subdomain) {
            $isValid = $this->deploymentService->validateSubdomainFormat($subdomain);
            $this->assertTrue($isValid, "Subdomain '{$subdomain}' should be valid");
        }

        // Test invalid subdomain formats
        $invalidSubdomains = ['', 'UPPERCASE', 'with_underscore', 'with.dot', 'with spaces'];
        
        foreach ($invalidSubdomains as $subdomain) {
            $isValid = $this->deploymentService->validateSubdomainFormat($subdomain);
            $this->assertFalse($isValid, "Subdomain '{$subdomain}' should be invalid");
        }
    }

    public function test_validates_branch_name()
    {
        // Test valid branch names
        $validBranches = ['main', 'staging', 'feature/new-feature', 'hotfix/bug-123'];
        
        foreach ($validBranches as $branch) {
            $isValid = $this->deploymentService->validateBranchName($branch);
            $this->assertTrue($isValid, "Branch '{$branch}' should be valid");
        }

        // Test invalid branch names
        $invalidBranches = ['', 'branch with spaces', 'branch..double-dot'];
        
        foreach ($invalidBranches as $branch) {
            $isValid = $this->deploymentService->validateBranchName($branch);
            $this->assertFalse($isValid, "Branch '{$branch}' should be invalid");
        }
    }

    public function test_validates_ssl_certificate_configuration()
    {
        // Test valid SSL configuration
        Config::set('deployment.ssl', [
            'enabled' => true,
            'provider' => 'letsencrypt',
            'email' => 'admin@example.com'
        ]);

        $validation = $this->deploymentService->validateSslConfig();
        $this->assertTrue($validation['valid']);

        // Test invalid SSL configuration
        Config::set('deployment.ssl', [
            'enabled' => true,
            'provider' => 'invalid_provider',
            'email' => 'invalid-email'
        ]);

        $validation = $this->deploymentService->validateSslConfig();
        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    public function test_validates_service_dependencies()
    {
        // Test valid service configuration
        Config::set('deployment.services', [
            'mysql' => [
                'enabled' => true,
                'version' => '8.0'
            ],
            'redis' => [
                'enabled' => true,
                'version' => '7.0'
            ]
        ]);

        $validation = $this->deploymentService->validateServiceDependencies();
        $this->assertTrue($validation['valid']);

        // Test invalid service configuration
        Config::set('deployment.services', [
            'mysql' => [
                'enabled' => true,
                'version' => 'invalid_version'
            ]
        ]);

        $validation = $this->deploymentService->validateServiceDependencies();
        $this->assertFalse($validation['valid']);
    }

    public function test_validates_deployment_secrets()
    {
        // Test with valid secrets configuration
        $secrets = [
            'SENTRY_DSN' => 'https://test@sentry.io/123',
            'FLAGSMITH_ENVIRONMENT_KEY' => 'ser.test_key',
            'GRAFANA_API_KEY' => 'test_api_key'
        ];

        $validation = $this->deploymentService->validateDeploymentSecrets($secrets);
        $this->assertTrue($validation['valid']);

        // Test with invalid secrets
        $invalidSecrets = [
            'SENTRY_DSN' => '', // Empty value
            'FLAGSMITH_ENVIRONMENT_KEY' => 'invalid_format',
            'GRAFANA_API_KEY' => null
        ];

        $validation = $this->deploymentService->validateDeploymentSecrets($invalidSecrets);
        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    public function test_validates_php_version_compatibility()
    {
        // Test valid PHP versions
        $validVersions = ['8.1', '8.2', '8.3'];
        
        foreach ($validVersions as $version) {
            $isValid = $this->deploymentService->validatePhpVersion($version);
            $this->assertTrue($isValid, "PHP version '{$version}' should be valid");
        }

        // Test invalid PHP versions
        $invalidVersions = ['7.4', '9.0', 'invalid'];
        
        foreach ($invalidVersions as $version) {
            $isValid = $this->deploymentService->validatePhpVersion($version);
            $this->assertFalse($isValid, "PHP version '{$version}' should be invalid");
        }
    }

    public function test_validates_node_version_compatibility()
    {
        // Test valid Node.js versions
        $validVersions = ['18', '20', '21'];
        
        foreach ($validVersions as $version) {
            $isValid = $this->deploymentService->validateNodeVersion($version);
            $this->assertTrue($isValid, "Node.js version '{$version}' should be valid");
        }

        // Test invalid Node.js versions
        $invalidVersions = ['14', '16', 'invalid'];
        
        foreach ($invalidVersions as $version) {
            $isValid = $this->deploymentService->validateNodeVersion($version);
            $this->assertFalse($isValid, "Node.js version '{$version}' should be invalid");
        }
    }

    public function test_validates_database_configuration()
    {
        // Test valid database configuration
        Config::set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => '3306',
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_password'
        ]);

        $validation = $this->deploymentService->validateDatabaseConfig('mysql');
        $this->assertTrue($validation['valid']);

        // Test invalid database configuration
        Config::set('database.connections.invalid', [
            'driver' => 'mysql',
            'host' => '',
            'database' => '',
            'username' => ''
        ]);

        $validation = $this->deploymentService->validateDatabaseConfig('invalid');
        $this->assertFalse($validation['valid']);
    }

    public function test_validates_cache_configuration()
    {
        // Test valid Redis cache configuration
        Config::set('cache.stores.redis', [
            'driver' => 'redis',
            'connection' => 'cache',
            'prefix' => 'laravel_cache'
        ]);

        $validation = $this->deploymentService->validateCacheConfig('redis');
        $this->assertTrue($validation['valid']);

        // Test invalid cache configuration
        Config::set('cache.stores.invalid', [
            'driver' => 'invalid_driver'
        ]);

        $validation = $this->deploymentService->validateCacheConfig('invalid');
        $this->assertFalse($validation['valid']);
    }

    public function test_validates_queue_configuration()
    {
        // Test valid queue configuration
        Config::set('queue.connections.redis', [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'default',
            'retry_after' => 90
        ]);

        $validation = $this->deploymentService->validateQueueConfig('redis');
        $this->assertTrue($validation['valid']);

        // Test invalid queue configuration
        Config::set('queue.connections.invalid', [
            'driver' => 'invalid_driver'
        ]);

        $validation = $this->deploymentService->validateQueueConfig('invalid');
        $this->assertFalse($validation['valid']);
    }

    public function test_validates_complete_deployment_configuration()
    {
        // Setup complete valid configuration
        Config::set('deployment', [
            'environments' => [
                'production' => [
                    'subdomain' => 'main',
                    'branch' => 'main',
                    'dokku_app' => 'restant-main'
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
                'retries' => 3
            ]
        ]);

        $validation = $this->deploymentService->validateCompleteConfiguration();
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
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