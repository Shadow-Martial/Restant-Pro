# Environment Configuration Management

This document describes the environment-specific configuration management system implemented for the automated deployment feature.

## Overview

The environment configuration management system provides:

- **Secure Environment Variable Management**: Encrypted storage and retrieval of sensitive configuration
- **Environment-Specific Configuration**: Separate configurations for production, staging, testing, and local environments
- **Secret Management**: Secure handling of sensitive data like passwords and API keys
- **Configuration Validation**: Automated validation of environment configurations
- **Testing Suite**: Comprehensive tests for configuration management

## Components

### 1. EnvironmentManager Service

The `EnvironmentManager` service (`app/Services/EnvironmentManager.php`) provides:

- Environment validation and detection
- Sensitive key identification and masking
- Configuration validation per environment
- Safe configuration logging

**Usage:**
```php
use App\Services\EnvironmentManager;

$manager = app(EnvironmentManager::class);

// Get current environment
$env = $manager->getCurrentEnvironment();

// Validate environment configuration
$errors = $manager->validateEnvironmentConfig('production');

// Check if key is sensitive
$isSensitive = $manager->isSensitiveKey('DB_PASSWORD');

// Get masked configuration for logging
$safeConfig = $manager->getSafeConfig();
```

### 2. SecretManager Service

The `SecretManager` service (`app/Services/SecretManager.php`) provides:

- Encrypted secret storage and retrieval
- Secret validation and strength checking
- Secret rotation capabilities
- Environment-specific secret management

**Usage:**
```php
use App\Services\SecretManager;

$manager = app(SecretManager::class);

// Store a secret
$manager->store('DB_PASSWORD', 'secure-password', 'production');

// Retrieve a secret
$password = $manager->get('DB_PASSWORD', 'production');

// Rotate a secret
$manager->rotate('API_KEY', 'new-api-key', 'production');

// Generate secure random secret
$secret = $manager->generateSecret(32);
```

### 3. Environment-Specific Configuration

Configuration files for different environments:

- `config/environments.php`: Environment-specific settings
- `.env.production.template`: Production environment template
- `.env.staging.template`: Staging environment template

**Environment Configurations:**

#### Production
- Debug disabled
- HTTPS enforced
- Optimized caching
- Error-level logging
- Security headers enabled

#### Staging
- Debug enabled
- Full error tracing
- Faster cache TTL
- Development tools enabled

#### Testing
- In-memory database
- Array cache
- External services disabled
- Debug enabled

#### Local
- File-based cache and sessions
- Debug enabled
- External services disabled
- Relaxed security

### 4. Console Commands

#### Environment Validation Command
```bash
# Validate current environment
php artisan env:validate

# Validate specific environment
php artisan env:validate --environment=production

# Test external service connections
php artisan env:validate --services

# Attempt to fix configuration issues
php artisan env:validate --fix
```

#### Secret Management Command
```bash
# Store a secret
php artisan secrets:manage store DB_PASSWORD

# Retrieve a secret
php artisan secrets:manage get DB_PASSWORD

# List all secrets
php artisan secrets:manage list

# Rotate a secret
php artisan secrets:manage rotate API_KEY

# Sync secrets from environment variables
php artisan secrets:manage sync

# Delete a secret
php artisan secrets:manage delete OLD_SECRET
```

## Environment Setup

### 1. Production Environment

1. Copy the production template:
   ```bash
   cp .env.production.template .env.production
   ```

2. Fill in required values:
   - `APP_KEY`: Generate with `php artisan key:generate`
   - `DB_PASSWORD`: Database password
   - `SENTRY_LARAVEL_DSN`: Sentry DSN for error tracking
   - `FLAGSMITH_ENVIRONMENT_KEY`: Flagsmith environment key
   - `GRAFANA_CLOUD_API_KEY`: Grafana Cloud API key

3. Validate configuration:
   ```bash
   php artisan env:validate --environment=production --services
   ```

### 2. Staging Environment

1. Copy the staging template:
   ```bash
   cp .env.staging.template .env.staging
   ```

2. Fill in required values (similar to production but with staging-specific settings)

3. Validate configuration:
   ```bash
   php artisan env:validate --environment=staging
   ```

## Security Features

### 1. Sensitive Key Protection

The system automatically identifies and protects sensitive configuration keys:

- `DB_PASSWORD`
- `REDIS_PASSWORD`
- `MAIL_PASSWORD`
- `SENTRY_LARAVEL_DSN`
- `FLAGSMITH_ENVIRONMENT_KEY`
- `GRAFANA_CLOUD_API_KEY`
- `APP_KEY`
- `JWT_SECRET`

### 2. Encrypted Secret Storage

Secrets are encrypted using Laravel's encryption system before storage:

- AES-256-CBC encryption
- Application key-based encryption
- JSON metadata storage
- Environment-specific isolation

### 3. Configuration Masking

Sensitive values are automatically masked in logs:
```php
// Original: "super-secret-password"
// Logged as: "[MASKED:a1b2c3d4]"
```

## Validation Rules

### Required Configuration Keys

#### Production
- `APP_KEY`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `SENTRY_LARAVEL_DSN`
- `FLAGSMITH_ENVIRONMENT_KEY`
- `GRAFANA_CLOUD_API_KEY`

#### Staging
- `APP_KEY`
- `DB_DATABASE`
- `DB_USERNAME`
- `SENTRY_LARAVEL_DSN`
- `FLAGSMITH_ENVIRONMENT_KEY`

### Environment-Specific Validations

#### Production
- `APP_DEBUG` must be `false`
- `APP_ENV` must be `production`
- `APP_URL` should use HTTPS

#### Staging
- `APP_ENV` must be `staging`

### Secret Strength Validation

- Minimum 8 characters for general secrets
- Minimum 12 characters for database passwords
- Minimum 32 characters for JWT secrets
- APP_KEY must be 32 characters or base64 encoded

## Testing

Run the environment configuration tests:

```bash
# Run all environment configuration tests
php artisan test --filter=EnvironmentConfigurationTest

# Run specific test methods
php artisan test --filter=test_environment_manager_validates_production_config
php artisan test --filter=test_secret_manager_stores_and_retrieves_secrets
```

## Deployment Integration

The environment configuration management integrates with the deployment process:

1. **Pre-deployment Validation**: Configuration is validated before deployment
2. **Secret Synchronization**: Secrets can be synced from environment variables
3. **Health Checks**: Post-deployment validation ensures configuration is working
4. **Rollback Support**: Configuration issues can trigger automatic rollback

## Best Practices

### 1. Secret Management
- Use the secret management system for all sensitive data
- Rotate secrets regularly using the rotation feature
- Never commit actual secrets to version control
- Use environment-specific secrets

### 2. Environment Configuration
- Always validate configuration before deployment
- Use templates to ensure consistency
- Test external service connections in staging
- Monitor configuration validation logs

### 3. Security
- Enable all security features in production
- Use HTTPS for all production URLs
- Implement proper session security
- Enable security headers

### 4. Performance
- Cache configuration in production
- Use Redis for sessions and cache in production
- Enable OPcache in production
- Optimize Laravel caches

## Troubleshooting

### Common Issues

1. **Missing Required Configuration**
   ```bash
   php artisan env:validate --fix
   ```

2. **Secret Storage Issues**
   - Check storage directory permissions
   - Verify APP_KEY is set
   - Check encryption configuration

3. **External Service Connection Failures**
   ```bash
   php artisan env:validate --services
   ```

4. **Performance Issues**
   - Check cache configuration
   - Verify Redis connection
   - Enable production optimizations

### Debug Commands

```bash
# Check current environment
php artisan tinker --execute="echo config('app.env')"

# List all secrets
php artisan secrets:manage list

# Test database connection
php artisan tinker --execute="DB::connection()->getPdo()"

# Test cache connection
php artisan tinker --execute="cache()->put('test', 'value'); echo cache()->get('test')"
```

## Integration with Other Services

The environment configuration management integrates with:

- **Sentry**: Environment-specific error tracking configuration
- **Flagsmith**: Feature flag environment management
- **Grafana Cloud**: Monitoring configuration per environment
- **Dokku**: Deployment environment variables
- **GitHub Actions**: CI/CD environment validation

This system ensures that each environment has the appropriate configuration for its purpose while maintaining security and reliability across all deployment stages.