# Deployment Health Checks

This document describes the comprehensive health check system implemented for deployment verification and monitoring.

## Overview

The health check system provides multiple endpoints and tools to verify that the application and its integrated services are functioning correctly after deployment. This is essential for automated deployment pipelines and ongoing monitoring.

## Health Check Endpoints

### Main Health Check Endpoint

**URL:** `/health`

**Description:** Provides an overall health status of the application and all integrated services.

**Response Format:**
```json
{
  "status": "healthy|degraded|unhealthy",
  "timestamp": "2024-01-01T12:00:00Z",
  "deployment_environment": "production",
  "version": "1.0.0",
  "services": {
    "database": "healthy|unhealthy",
    "cache": "healthy|unhealthy", 
    "sentry": "healthy|unhealthy|disabled",
    "flagsmith": "healthy|unhealthy|disabled",
    "grafana": "healthy|unhealthy|disabled"
  },
  "ssl": {
    "status": "valid|invalid|not_applicable",
    "domain": "example.com",
    "days_until_expiry": 45
  }
}
```

**Status Codes:**
- `200`: Application is healthy or degraded but operational
- `503`: Application is unhealthy (critical services down)

### Individual Service Health Checks

#### Database Health Check
**URL:** `/health/database`

Verifies:
- Database connection
- Basic query execution
- Migrations table accessibility

#### Sentry Health Check
**URL:** `/health/sentry`

Verifies:
- Sentry configuration
- Message capture functionality
- Exception capture functionality

#### Flagsmith Health Check
**URL:** `/health/flagsmith`

Verifies:
- Flagsmith API connectivity
- Feature flag retrieval
- Fallback mechanism functionality

#### Grafana Cloud Health Check
**URL:** `/health/grafana`

Verifies:
- Grafana Cloud API connectivity
- Metrics endpoint accessibility

#### SSL Certificate Health Check
**URL:** `/health/ssl`

Verifies:
- SSL certificate validity
- Certificate expiration status
- Certificate chain integrity

## Command Line Tools

### Deployment Health Check Command

**Usage:**
```bash
php artisan deployment:health-check [options]
```

**Options:**
- `--timeout=30`: Timeout in seconds for health checks
- `--critical-only`: Only check critical services (database, cache)
- `--format=table|json`: Output format

**Examples:**
```bash
# Basic health check
php artisan deployment:health-check

# Quick critical services check
php artisan deployment:health-check --critical-only --timeout=10

# JSON output for automation
php artisan deployment:health-check --format=json
```

### Shell Scripts

#### Linux/Unix Script
**File:** `scripts/deployment-health-check.sh`

**Usage:**
```bash
# Basic usage
./scripts/deployment-health-check.sh

# With custom configuration
HEALTH_CHECK_URL=https://myapp.com/health \
MAX_RETRIES=5 \
RETRY_DELAY=15 \
./scripts/deployment-health-check.sh
```

#### Windows Script
**File:** `scripts/deployment-health-check.bat`

**Usage:**
```cmd
REM Basic usage
scripts\deployment-health-check.bat

REM With custom configuration
set HEALTH_CHECK_URL=https://myapp.com/health
set MAX_RETRIES=5
scripts\deployment-health-check.bat
```

## GitHub Actions Integration

### Reusable Workflow

**File:** `.github/workflows/deployment-health-check.yml`

**Usage in deployment workflow:**
```yaml
jobs:
  deploy:
    # ... deployment steps ...
    
  health-check:
    needs: deploy
    uses: ./.github/workflows/deployment-health-check.yml
    with:
      environment: production
      health_check_url: https://myapp.com/health
      max_retries: 10
      retry_delay: 30
      timeout: 30
```

## Configuration

### Environment Variables

```bash
# Health check configuration
HEALTH_CHECKS_ENABLED=true
HEALTH_CHECK_DB_TIMEOUT=5
HEALTH_CHECK_CACHE_TIMEOUT=3
HEALTH_CHECK_SENTRY_TIMEOUT=10
HEALTH_CHECK_FLAGSMITH_TIMEOUT=10
HEALTH_CHECK_GRAFANA_TIMEOUT=10
HEALTH_CHECK_SSL_ENABLED=true
HEALTH_CHECK_SSL_WARNING_DAYS=30
HEALTH_CHECK_SSL_CRITICAL_DAYS=7

# Monitoring integration
HEALTH_CHECK_SEND_TO_SENTRY=false
HEALTH_CHECK_SEND_TO_GRAFANA=true
HEALTH_CHECK_LOG_FAILURES=true

# Deployment verification
HEALTH_CHECK_VERIFY_MIGRATIONS=true
HEALTH_CHECK_VERIFY_CACHE_CLEAR=true
HEALTH_CHECK_MAX_DEPLOYMENT_TIME=300
```

### Configuration File

**File:** `config/health.php`

Contains detailed configuration options for:
- Critical vs optional services
- Timeout values
- SSL certificate validation settings
- Monitoring integration settings
- Circuit breaker configuration

## Service Status Definitions

### Status Values

- **healthy**: Service is fully operational
- **degraded**: Service has issues but is still functional
- **unhealthy**: Service is not working properly
- **disabled**: Service is intentionally disabled
- **unknown**: Service status could not be determined

### Critical vs Optional Services

**Critical Services** (deployment fails if unhealthy):
- Database
- Cache

**Optional Services** (deployment can proceed if degraded):
- Sentry (error monitoring)
- Flagsmith (feature flags)
- Grafana Cloud (metrics)
- SSL Certificate

## Deployment Integration

### Pre-deployment Checks

1. **Configuration Validation**: Verify all required environment variables
2. **Service Connectivity**: Test connections to external services
3. **SSL Certificate**: Validate certificate status

### Post-deployment Verification

1. **Application Readiness**: Wait for application to respond
2. **Database Migrations**: Verify migrations completed successfully
3. **Cache Functionality**: Test cache read/write operations
4. **Service Integration**: Verify all monitoring services are connected
5. **SSL Validation**: Confirm SSL certificate is valid and properly configured

### Rollback Triggers

Automatic rollback is triggered if:
- Critical services (database, cache) are unhealthy
- Application fails to respond within timeout period
- SSL certificate is expired or invalid
- More than 2 optional services are unhealthy

## Monitoring and Alerting

### Health Check Metrics

The system automatically sends metrics to Grafana Cloud:
- `laravel_health_check_duration_seconds`: Time taken for health checks
- `laravel_health_check_status`: Status of each service (0=unhealthy, 1=healthy)
- `laravel_deployment_health_check`: Overall deployment health status

### Error Reporting

Failed health checks are reported to:
- Application logs
- Sentry (if configured)
- Grafana Cloud (if configured)

### Notifications

Deployment status notifications can be sent to:
- Slack webhooks
- Email
- Custom webhook endpoints

## Troubleshooting

### Common Issues

1. **Database Connection Failures**
   - Check database credentials
   - Verify network connectivity
   - Ensure database server is running

2. **Cache Issues**
   - Verify Redis/cache server status
   - Check cache configuration
   - Test cache connectivity

3. **SSL Certificate Problems**
   - Check certificate expiration
   - Verify domain configuration
   - Ensure proper certificate chain

4. **Service Integration Failures**
   - Verify API keys and credentials
   - Check service endpoint URLs
   - Test network connectivity to external services

### Debug Mode

Enable detailed logging by setting:
```bash
HEALTH_CHECK_LOG_FAILURES=true
LOG_LEVEL=debug
```

### Manual Testing

Test individual components:
```bash
# Test database
php artisan tinker
>>> DB::select('SELECT 1 as test');

# Test cache
>>> Cache::put('test', 'value', 10);
>>> Cache::get('test');

# Test health endpoints
curl -v http://localhost/health
curl -v http://localhost/health/database
```

## Security Considerations

1. **Endpoint Access**: Health check endpoints should be accessible without authentication for monitoring purposes
2. **Information Disclosure**: Health checks should not expose sensitive configuration details
3. **Rate Limiting**: Consider rate limiting health check endpoints to prevent abuse
4. **Network Security**: Ensure health check traffic is properly secured in production

## Performance Impact

- Health checks are designed to be lightweight and fast
- Individual service checks timeout after 10 seconds by default
- Overall health check should complete within 30 seconds
- Caching is used where appropriate to reduce load on external services

## Best Practices

1. **Regular Testing**: Test health checks in staging before production deployment
2. **Monitoring**: Set up alerts for health check failures
3. **Documentation**: Keep health check documentation updated
4. **Automation**: Integrate health checks into CI/CD pipelines
5. **Fallback Handling**: Ensure graceful degradation when optional services are unavailable