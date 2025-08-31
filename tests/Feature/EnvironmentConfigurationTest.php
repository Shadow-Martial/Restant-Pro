<?php

namespace Tests\Feature;

use App\Services\EnvironmentManager;
use App\Services\SecretManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EnvironmentConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected EnvironmentManager $environmentManager;
    protected SecretManager $secretManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->environmentManager = app(EnvironmentManager::class);
        $this->secretManager = app(SecretManager::class);
    }

    public function test_environment_manager_validates_current_environment()
    {
        $environment = $this->environmentManager->getCurrentEnvironment();
        $this->assertContains($environment, EnvironmentManager::ENVIRONMENTS);
    }

    public function test_environment_manager_validates_supported_environments()
    {
        $this->assertTrue($this->environmentManager->isValidEnvironment('production'));
        $this->assertTrue($this->environmentManager->isValidEnvironment('staging'));
        $this->assertTrue($this->environmentManager->isValidEnvironment('testing'));
        $this->assertTrue($this->environmentManager->isValidEnvironment('local'));
        $this->assertFalse($this->environmentManager->isValidEnvironment('invalid'));
    }

    public function test_environment_manager_identifies_sensitive_keys()
    {
        $this->assertTrue($this->environmentManager->isSensitiveKey('DB_PASSWORD'));
        $this->assertTrue($this->environmentManager->isSensitiveKey('APP_KEY'));
        $this->assertTrue($this->environmentManager->isSensitiveKey('SENTRY_LARAVEL_DSN'));
        $this->assertFalse($this->environmentManager->isSensitiveKey('APP_NAME'));
        $this->assertFalse($this->environmentManager->isSensitiveKey('APP_ENV'));
    }

    public function test_environment_manager_masks_sensitive_values()
    {
        $sensitiveValue = 'super-secret-password';
        $maskedValue = $this->environmentManager->maskSensitiveValue('DB_PASSWORD', $sensitiveValue);
        
        $this->assertStringStartsWith('[MASKED:', $maskedValue);
        $this->assertStringEndsWith(']', $maskedValue);
        $this->assertNotEquals($sensitiveValue, $maskedValue);

        $normalValue = 'MyApp';
        $unmaskedValue = $this->environmentManager->maskSensitiveValue('APP_NAME', $normalValue);
        $this->assertEquals($normalValue, $unmaskedValue);
    }

    public function test_environment_manager_validates_production_config()
    {
        // Mock production environment
        Config::set('app.env', 'production');
        
        // Set required environment variables
        putenv('APP_KEY=base64:' . base64_encode(str_repeat('a', 32)));
        putenv('DB_DATABASE=test_db');
        putenv('DB_USERNAME=test_user');
        putenv('DB_PASSWORD=test_password');
        putenv('SENTRY_LARAVEL_DSN=https://test@sentry.io/123');
        putenv('FLAGSMITH_ENVIRONMENT_KEY=test_key');
        putenv('GRAFANA_CLOUD_API_KEY=test_api_key');

        $errors = $this->environmentManager->validateEnvironmentConfig('production');
        $this->assertEmpty($errors);

        // Test with missing required key
        putenv('DB_PASSWORD=');
        $errors = $this->environmentManager->validateEnvironmentConfig('production');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('DB_PASSWORD', implode(' ', $errors));
    }

    public function test_environment_manager_validates_staging_config()
    {
        Config::set('app.env', 'staging');
        
        // Set required environment variables for staging
        putenv('APP_KEY=base64:' . base64_encode(str_repeat('a', 32)));
        putenv('DB_DATABASE=test_db');
        putenv('DB_USERNAME=test_user');
        putenv('SENTRY_LARAVEL_DSN=https://test@sentry.io/123');
        putenv('FLAGSMITH_ENVIRONMENT_KEY=test_key');

        $errors = $this->environmentManager->validateEnvironmentConfig('staging');
        $this->assertEmpty($errors);
    }

    public function test_secret_manager_stores_and_retrieves_secrets()
    {
        $key = 'TEST_SECRET';
        $value = 'super-secret-value';
        $environment = 'testing';

        // Store secret
        $result = $this->secretManager->store($key, $value, $environment);
        $this->assertTrue($result);

        // Retrieve secret
        $retrievedValue = $this->secretManager->get($key, $environment);
        $this->assertEquals($value, $retrievedValue);

        // Delete secret
        $deleteResult = $this->secretManager->delete($key, $environment);
        $this->assertTrue($deleteResult);

        // Verify deletion
        $deletedValue = $this->secretManager->get($key, $environment);
        $this->assertNull($deletedValue);
    }

    public function test_secret_manager_validates_secret_strength()
    {
        // Test weak password
        $errors = $this->secretManager->validateSecret('DB_PASSWORD', 'weak');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('at least 12 characters', implode(' ', $errors));

        // Test strong password
        $errors = $this->secretManager->validateSecret('DB_PASSWORD', 'very-strong-password-123');
        $this->assertEmpty($errors);

        // Test APP_KEY validation
        $errors = $this->secretManager->validateSecret('APP_KEY', 'short');
        $this->assertNotEmpty($errors);

        $errors = $this->secretManager->validateSecret('APP_KEY', str_repeat('a', 32));
        $this->assertEmpty($errors);
    }

    public function test_secret_manager_generates_secure_secrets()
    {
        $secret1 = $this->secretManager->generateSecret();
        $secret2 = $this->secretManager->generateSecret();

        $this->assertEquals(64, strlen($secret1)); // 32 bytes = 64 hex chars
        $this->assertEquals(64, strlen($secret2));
        $this->assertNotEquals($secret1, $secret2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $secret1);
    }

    public function test_secret_manager_rotates_secrets()
    {
        $key = 'TEST_ROTATION';
        $originalValue = 'original-secret';
        $newValue = 'new-secret-value';
        $environment = 'testing';

        // Store original secret
        $this->secretManager->store($key, $originalValue, $environment);

        // Rotate secret
        $result = $this->secretManager->rotate($key, $newValue, $environment);
        $this->assertTrue($result);

        // Verify new value
        $retrievedValue = $this->secretManager->get($key, $environment);
        $this->assertEquals($newValue, $retrievedValue);

        // Verify backup exists
        $backupValue = $this->secretManager->get("{$key}_backup", $environment);
        $this->assertEquals($originalValue, $backupValue);
    }

    public function test_secret_manager_lists_secrets()
    {
        $environment = 'testing';
        
        // Store multiple secrets
        $this->secretManager->store('SECRET_1', 'value1', $environment);
        $this->secretManager->store('SECRET_2', 'value2', $environment);

        // List secrets
        $secrets = $this->secretManager->list($environment);

        $this->assertArrayHasKey('SECRET_1', $secrets);
        $this->assertArrayHasKey('SECRET_2', $secrets);
        $this->assertEquals($environment, $secrets['SECRET_1']['environment']);
        $this->assertArrayHasKey('created_at', $secrets['SECRET_1']);
    }

    public function test_environment_specific_configurations_are_applied()
    {
        // Test production configuration
        Config::set('app.env', 'production');
        $prodConfig = config('environments.production');
        
        $this->assertFalse($prodConfig['app']['debug']);
        $this->assertEquals('error', $prodConfig['app']['log_level']);
        $this->assertTrue($prodConfig['security']['force_https']);

        // Test staging configuration
        $stagingConfig = config('environments.staging');
        
        $this->assertTrue($stagingConfig['app']['debug']);
        $this->assertEquals('debug', $stagingConfig['app']['log_level']);
        $this->assertFalse($stagingConfig['security']['content_security_policy']);

        // Test testing configuration
        $testingConfig = config('environments.testing');
        
        $this->assertEquals('array', $testingConfig['cache']['default']);
        $this->assertFalse($testingConfig['monitoring']['sentry']['enabled']);
    }

    public function test_configuration_validation_command_works()
    {
        $this->artisan('env:validate')
            ->assertExitCode(0);
    }

    public function test_secret_management_command_works()
    {
        // Test storing a secret
        $this->artisan('secrets:manage', [
            'action' => 'store',
            'key' => 'TEST_CLI_SECRET',
            'value' => 'test-value-from-cli',
        ])->assertExitCode(0);

        // Test retrieving the secret
        $this->artisan('secrets:manage', [
            'action' => 'get',
            'key' => 'TEST_CLI_SECRET',
        ])->assertExitCode(0);

        // Test listing secrets
        $this->artisan('secrets:manage', [
            'action' => 'list',
        ])->assertExitCode(0);

        // Test deleting the secret
        $this->artisan('secrets:manage', [
            'action' => 'delete',
            'key' => 'TEST_CLI_SECRET',
        ])->expectsConfirmation('Are you sure you want to delete secret \'TEST_CLI_SECRET\' for environment \'testing\'?', 'yes')
        ->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        $testVars = [
            'APP_KEY',
            'DB_DATABASE',
            'DB_USERNAME', 
            'DB_PASSWORD',
            'SENTRY_LARAVEL_DSN',
            'FLAGSMITH_ENVIRONMENT_KEY',
            'GRAFANA_CLOUD_API_KEY',
        ];

        foreach ($testVars as $var) {
            putenv("{$var}=");
        }

        parent::tearDown();
    }
}