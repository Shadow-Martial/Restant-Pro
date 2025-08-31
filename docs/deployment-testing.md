# Deployment Testing Documentation

This document describes the comprehensive testing suite for the automated deployment system.

## Overview

The deployment testing suite validates all aspects of the automated deployment system including:

- **Unit Tests**: Deployment configuration validation
- **Integration Tests**: Monitoring services integration
- **Feature Tests**: End-to-end deployment workflows
- **Rollback Tests**: Rollback scenario validation

## Test Structure

### Unit Tests (`tests/Unit/`)

#### DeploymentConfigurationTest.php
Tests the core deployment service configuration functionality:

- Environment detection and configuration loading
- Dokku environment detection
- SSL configuration validation
- Monitoring service configuration
- Health check configuration
- Deployment information gathering

**Requirements Covered**: 2.3, 5.1, 5.2

### Integration Tests (`tests/Integration/`)

#### MonitoringServicesIntegrationTest.php
Tests integration with external monitoring services:

- Sentry error tracking integration
- Flagsmith feature flag integration
- Grafana Cloud metrics integration
- Health check validation for all services
- Graceful degradation when services are unavailable

**Requirements Covered**: 6.1, 6.2, 7.2, 7.3, 8.1, 8.3

### Feature Tests (`tests/Feature/`)

#### EndToEndDeploymentTest.php
Tests complete deployment workflows:

- Production deployment workflow
- Staging deployment workflow
- Deployment with monitoring services
- Health check validation
- SSL certificate validation
- Notification workflows
- Database migrations
- Asset compilation

**Requirements Covered**: 1.1, 1.2, 3.1, 3.2, 3.3, 3.4, 4.3

#### DeploymentRollbackTest.php
Tests rollback scenarios and functionality:

- Automatic rollback on deployment failure
- Manual rollback with specific releases
- Rollback verification and health checks
- Rollback notifications
- Multiple environment rollbacks
- Configuration validation for rollbacks

**Requirements Covered**: 4.1, 4.2, 4.4

#### DeploymentNotificationTest.php
Tests deployment notification system:

- Deployment started notifications
- Success and failure notifications
- Rollback notifications
- Multiple notification channels (Slack, email, webhook)
- Notification command testing

**Requirements Covered**: 9.1, 9.2, 9.3, 9.4

#### EnvironmentConfigurationTest.php
Tests environment-specific configuration management:

- Environment validation
- Secret management
- Configuration validation for different environments
- Environment-specific settings application

**Requirements Covered**: 5.1, 5.2, 5.3, 5.4

#### DeploymentTestSuite.php
Orchestrates and validates the entire deployment system:

- Service binding validation
- Command registration verification
- Route and middleware validation
- Configuration structure validation
- Database table validation

## Running Tests

### Prerequisites

1. Ensure all dependencies are installed:
   ```bash
   composer install
   ```

2. Set up test environment:
   ```bash
   cp .env.testing.example .env.testing
   ```

### Running All Deployment Tests

#### Linux/macOS:
```bash
./scripts/run-deployment-tests.sh
```

#### Windows:
```batch
scripts\run-deployment-tests.bat
```

### Running Specific Test Suites

#### Unit Tests Only:
```bash
./vendor/bin/phpunit --configuration phpunit.deployment.xml --testsuite "Deployment Unit Tests"
```

#### Integration Tests Only:
```bash
./vendor/bin/phpunit --configuration phpunit.deployment.xml --testsuite "Deployment Integration Tests"
```

#### Feature Tests Only:
```bash
./vendor/bin/phpunit --configuration phpunit.deployment.xml --testsuite "Deployment Feature Tests"
```

### Running Individual Test Files

```bash
./vendor/bin/phpunit tests/Unit/DeploymentConfigurationTest.php
./vendor/bin/phpunit tests/Integration/MonitoringServicesIntegrationTest.php
./vendor/bin/phpunit tests/Feature/EndToEndDeploymentTest.php
./vendor/bin/phpunit tests/Feature/DeploymentRollbackTest.php
```

### Running with Coverage

```bash
./vendor/bin/phpunit --configuration phpunit.deployment.xml --coverage-html coverage/deployment
```

## Test Configuration

### PHPUnit Configuration

The deployment tests use a dedicated PHPUnit configuration file (`phpunit.deployment.xml`) that:

- Isolates deployment tests from other application tests
- Sets up proper test environment variables
- Configures coverage reporting for deployment-related code
- Disables external services during testing

### Environment Variables

Key environment variables for testing:

```bash
APP_ENV=testing
DEPLOYMENT_TESTING=true
DOKKU_HOST=test.example.com
DEPLOYMENT_SSH_KEY=/path/to/test/key

# Disable external services
DEPLOYMENT_SLACK_ENABLED=false
DEPLOYMENT_EMAIL_ENABLED=false
DEPLOYMENT_WEBHOOK_ENABLED=false
SENTRY_LARAVEL_DSN=
FLAGSMITH_ENVIRONMENT_KEY=
GRAFANA_CLOUD_API_KEY=
```

## Test Mocking

The tests use extensive mocking to avoid external dependencies:

### HTTP Requests
- All external API calls (Sentry, Flagsmith, Grafana, Slack) are mocked using Laravel's HTTP fake
- Webhook notifications are intercepted and validated

### Process Execution
- Dokku SSH commands are mocked using Laravel's Process fake
- Git operations are simulated
- System commands are intercepted

### Database Operations
- Uses in-memory SQLite database for fast test execution
- Database migrations are run automatically
- Test data is isolated between tests

## Test Data and Fixtures

### Configuration Fixtures
Tests use predefined configuration arrays for:
- Environment settings (production, staging, testing)
- Monitoring service configurations
- Notification channel settings
- Rollback configurations

### Mock Responses
Standardized mock responses for:
- Sentry API responses
- Flagsmith feature flag responses
- Grafana Cloud metric ingestion
- Dokku command outputs
- Health check endpoints

## Continuous Integration

### GitHub Actions Integration

The deployment tests are designed to run in CI/CD pipelines:

```yaml
- name: Run Deployment Tests
  run: |
    php artisan config:clear --env=testing
    ./vendor/bin/phpunit --configuration phpunit.deployment.xml --coverage-clover coverage.xml
```

### Test Parallelization

Tests are organized to support parallel execution:
- Unit tests have no external dependencies
- Integration tests mock all external services
- Feature tests use isolated test databases
- No shared state between test classes

## Troubleshooting

### Common Issues

1. **Missing Dependencies**
   ```bash
   composer install --dev
   ```

2. **Permission Issues (Linux/macOS)**
   ```bash
   chmod +x scripts/run-deployment-tests.sh
   ```

3. **Database Issues**
   ```bash
   php artisan migrate:fresh --env=testing
   ```

4. **Cache Issues**
   ```bash
   php artisan config:clear --env=testing
   php artisan cache:clear --env=testing
   ```

### Debug Mode

Run tests with verbose output:
```bash
./vendor/bin/phpunit --configuration phpunit.deployment.xml --verbose --debug
```

### Test Isolation

If tests are interfering with each other:
```bash
./vendor/bin/phpunit --configuration phpunit.deployment.xml --process-isolation
```

## Coverage Reports

### HTML Coverage Report
After running tests with coverage, open:
```
coverage/deployment/index.html
```

### Coverage Targets
- **Unit Tests**: 95%+ coverage of deployment services
- **Integration Tests**: 90%+ coverage of monitoring integrations
- **Feature Tests**: 85%+ coverage of end-to-end workflows

### Coverage Exclusions
The following are excluded from coverage requirements:
- Third-party vendor code
- Configuration files
- Database migrations
- Blade templates

## Performance Considerations

### Test Execution Time
- Unit tests: < 10 seconds
- Integration tests: < 30 seconds
- Feature tests: < 60 seconds
- Complete suite: < 2 minutes

### Memory Usage
- Tests use in-memory database to minimize I/O
- HTTP mocking reduces network overhead
- Process mocking eliminates system command execution

### Optimization Tips
1. Run unit tests first (fastest feedback)
2. Use `--stop-on-failure` for quick debugging
3. Run specific test methods during development
4. Use coverage only when needed (slower execution)

## Maintenance

### Adding New Tests

1. **Unit Tests**: Add to `tests/Unit/` for new deployment services
2. **Integration Tests**: Add to `tests/Integration/` for new monitoring services
3. **Feature Tests**: Add to `tests/Feature/` for new deployment workflows

### Updating Mocks

When external APIs change:
1. Update mock responses in test files
2. Verify integration tests still pass
3. Update documentation if needed

### Configuration Updates

When deployment configuration changes:
1. Update test configuration in `setupTestConfiguration()`
2. Update PHPUnit configuration if needed
3. Update environment variable documentation

## Security Considerations

### Test Data
- No real credentials in test files
- Use placeholder values for sensitive data
- Mock all external service calls

### Test Environment
- Isolated from production systems
- No real deployments during testing
- All SSH commands are mocked

### Secrets Management
- Test secrets are not real
- Secret validation tests use dummy data
- No production secrets in test environment