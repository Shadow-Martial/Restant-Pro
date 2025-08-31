# Deployment Testing Suite

This comprehensive testing suite validates all aspects of the automated deployment system for the Laravel multi-tenant SaaS platform.

## Overview

The deployment testing suite covers:

- **Unit Tests**: Configuration validation, service initialization, and component testing
- **Integration Tests**: Monitoring services integration and workflow testing
- **Feature Tests**: End-to-end deployment scenarios and rollback testing
- **Scenario Tests**: Real-world deployment patterns and edge cases

## Test Structure

```
tests/
├── Unit/
│   ├── DeploymentConfigurationTest.php    # Configuration validation tests
│   └── DeploymentValidationTest.php       # Input validation and security tests
├── Integration/
│   ├── MonitoringServicesIntegrationTest.php    # Sentry, Flagsmith, Grafana integration
│   └── DeploymentWorkflowIntegrationTest.php    # Complete workflow testing
├── Feature/
│   ├── EndToEndDeploymentTest.php         # Complete deployment scenarios
│   ├── DeploymentRollbackTest.php         # Rollback scenarios and validation
│   ├── DeploymentNotificationTest.php     # Notification system testing
│   ├── DeploymentHealthCheckTest.php      # Health check validation
│   ├── EnvironmentConfigurationTest.php   # Environment-specific testing
│   ├── FlagsmithIntegrationTest.php       # Feature flag integration
│   ├── GrafanaCloudIntegrationTest.php    # Monitoring integration
│   └── DeploymentTestSuite.php           # Orchestrated test execution
├── Commands/
│   └── RunDeploymentTestsCommand.php      # CLI test runner
├── DeploymentTestRunner.php               # Test orchestration and reporting
├── DeploymentTestListener.php             # PHPUnit test listener for reporting
└── README.md                             # This documentation
```

## Running Tests

### Prerequisites

1. Ensure you're in the testing environment:
   ```bash
   export APP_ENV=testing
   ```

2. Install test dependencies:
   ```bash
   composer install --dev
   ```

3. Configure test database:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

### Running All Tests

```bash
# Run all deployment tests
php artisan deployment:test

# Run with detailed reporting
php artisan deployment:test --report --coverage --verbose

# Run using PHPUnit directly
./vendor/bin/phpunit --configuration phpunit.deployment.xml
```

### Running Specific Test Suites

```bash
# Run only unit tests
php artisan deployment:test --suite=unit

# Run only integration tests
php artisan deployment:test --suite=integration

# Run only feature tests
php artisan deployment:test --suite=feature

# Run specific test class
./vendor/bin/phpunit tests/Unit/DeploymentConfigurationTest.php
```

### Running Deployment Scenarios

```bash
# Run specific deployment scenario
php artisan deployment:test --scenario=successful_deployment
php artisan deployment:test --scenario=failed_deployment_with_rollback
php artisan deployment:test --scenario=zero_downtime_deployment
php artisan deployment:test --scenario=canary_deployment
php artisan deployment:test --scenario=blue_green_deployment
```

## Test Categories

### Unit Tests

**DeploymentConfigurationTest.php**
- Configuration validation and parsing
- Environment-specific settings
- Monitoring service configuration
- Notification channel setup
- Rollback and health check configuration

**DeploymentValidationTest.php**
- Environment variable validation
- Dokku configuration validation
- Subdomain and branch name validation
- SSL certificate configuration
- Service dependency validation
- Security and compliance validation

### Integration Tests

**MonitoringServicesIntegrationTest.php**
- Sentry error tracking integration
- Flagsmith feature flag integration
- Grafana Cloud metrics integration
- Service health checks and connectivity
- Error handling and graceful degradation
- Performance monitoring and alerting

**DeploymentWorkflowIntegrationTest.php**
- Complete deployment workflow testing
- Multi-environment deployment coordination
- Database migration integration
- Asset compilation and deployment
- SSL certificate provisioning
- Environment variable management

### Feature Tests

**EndToEndDeploymentTest.php**
- Complete production deployment workflow
- Zero-downtime deployment strategies
- Canary release deployments
- Blue-green deployment patterns
- Performance regression detection
- Security vulnerability scanning
- Multi-region deployment synchronization

**DeploymentRollbackTest.php**
- Automatic rollback on failure
- Manual rollback procedures
- Database migration reversal
- Asset and configuration restoration
- Service dependency restoration
- Traffic switching during rollback
- Comprehensive rollback validation

## Test Configuration

### Environment Variables

```bash
# Core testing configuration
APP_ENV=testing
DEPLOYMENT_TESTING=true

# Disable external services during testing
SENTRY_ENABLED=false
FLAGSMITH_ENABLED=false
GRAFANA_ENABLED=false
DEPLOYMENT_NOTIFICATIONS_ENABLED=false

# Mock external dependencies
MOCK_EXTERNAL_SERVICES=true
MOCK_DOKKU_COMMANDS=true
MOCK_HTTP_REQUESTS=true

# Test-specific configuration
DEPLOYMENT_TEST_DOKKU_HOST=test.example.com
DEPLOYMENT_TEST_SSH_KEY=/path/to/test/key
```

### PHPUnit Configuration

The `phpunit.deployment.xml` configuration file provides:

- Separate test suites for different test types
- Code coverage reporting
- Test result logging in JUnit format
- Environment-specific test configuration
- Custom test listener for enhanced reporting

## Test Scenarios

### Successful Deployment Scenario

1. Configuration validation
2. Pre-deployment checks
3. Deployment execution
4. Health checks
5. Monitoring verification
6. Success notification

### Failed Deployment with Rollback Scenario

1. Configuration validation
2. Pre-deployment checks
3. Deployment execution (fails)
4. Automatic rollback trigger
5. Rollback execution
6. Rollback verification
7. Failure notification

### Zero-Downtime Deployment Scenario

1. Instance scaling
2. Load balancer configuration
3. Gradual instance replacement
4. Traffic validation
5. Old instance termination
6. Downtime measurement (should be 0)

### Canary Deployment Scenario

1. Canary environment creation
2. Canary deployment
3. Traffic splitting (10% to canary)
4. Canary monitoring
5. Performance validation
6. Full rollout or rollback

### Blue-Green Deployment Scenario

1. Green environment creation
2. Green deployment
3. Green validation
4. Traffic switching
5. Blue environment deactivation

## Monitoring and Reporting

### Test Reports

Test execution generates several types of reports:

1. **Console Output**: Real-time test progress and results
2. **JSON Reports**: Detailed test results in `storage/logs/`
3. **Coverage Reports**: Code coverage analysis in HTML format
4. **JUnit XML**: Test results in JUnit format for CI/CD integration

### Test Metrics

The test suite tracks:

- Test execution time and performance
- Memory usage during tests
- Code coverage percentages
- Test success/failure rates
- Deployment scenario validation
- Integration service connectivity

### Continuous Integration

For CI/CD pipelines, use:

```bash
# Run tests with CI-friendly output
./vendor/bin/phpunit --configuration phpunit.deployment.xml --log-junit storage/logs/deployment-test-results.xml

# Generate coverage report for CI
./vendor/bin/phpunit --configuration phpunit.deployment.xml --coverage-clover storage/logs/coverage.xml
```

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Ensure test database is configured (SQLite in-memory recommended)
   - Check `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`

2. **External Service Timeouts**
   - Verify `MOCK_EXTERNAL_SERVICES=true` is set
   - Check HTTP fake configurations in test setup

3. **Permission Errors**
   - Ensure storage/logs directory is writable
   - Check SSH key permissions for Dokku tests

4. **Memory Issues**
   - Increase PHP memory limit for large test suites
   - Use `--stop-on-failure` to debug specific issues

### Debug Mode

Enable verbose logging for debugging:

```bash
# Run with maximum verbosity
php artisan deployment:test --verbose

# Enable debug logging
export LOG_LEVEL=debug
php artisan deployment:test
```

## Best Practices

### Writing New Tests

1. **Follow Naming Conventions**
   - Test methods should start with `test_`
   - Use descriptive names that explain the scenario
   - Group related tests in the same class

2. **Use Proper Setup and Teardown**
   - Clean up environment variables in `tearDown()`
   - Reset configuration state between tests
   - Mock external dependencies consistently

3. **Assert Meaningful Results**
   - Test both success and failure scenarios
   - Verify side effects and state changes
   - Include performance and security validations

4. **Document Test Purpose**
   - Add docblocks explaining test scenarios
   - Include requirements references
   - Document any special setup requirements

### Test Maintenance

1. **Regular Updates**
   - Update tests when deployment configuration changes
   - Add tests for new deployment features
   - Remove or update obsolete test scenarios

2. **Performance Monitoring**
   - Monitor test execution time
   - Optimize slow-running tests
   - Balance thoroughness with execution speed

3. **Coverage Analysis**
   - Maintain high code coverage (>90%)
   - Identify and test edge cases
   - Ensure all deployment paths are tested

## Requirements Coverage

This test suite validates all requirements from the deployment specification:

- **Requirement 1.3**: Automated deployment testing and validation
- **Requirement 4.2**: Rollback scenario testing and verification
- **Requirement 6.2**: Sentry integration testing and error capture
- **Requirement 7.3**: Flagsmith integration testing and feature flag validation
- **Requirement 8.3**: Grafana Cloud integration testing and metrics validation

## Contributing

When adding new tests:

1. Follow the existing test structure and patterns
2. Add appropriate documentation and comments
3. Ensure tests are deterministic and reliable
4. Include both positive and negative test cases
5. Update this README with any new test categories or scenarios