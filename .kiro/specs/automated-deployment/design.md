# Design Document

## Overview

This design implements a comprehensive automated deployment system for the Laravel multi-tenant SaaS platform using GitHub Actions and Dokku. The system will deploy applications to subdomain-based environments (restant.{subdomain}.susankshakya.com.np) with integrated monitoring via Sentry, feature flag management through self-hosted Flagsmith, and observability through Grafana Cloud.

## Architecture

### Deployment Flow
```
GitHub Push → GitHub Actions → Dokku Git Deploy → Container Build → Health Check → Live
     ↓              ↓              ↓               ↓              ↓         ↓
  Trigger      Build Assets    Run Migrations   Start Services   Verify   Notify
```

### Infrastructure Components

1. **GitHub Actions Runner**: Executes CI/CD pipeline
2. **Dokku Server**: Ubuntu 24.04.3 (209.50.227.94) with Git-based deployment
3. **Subdomain Routing**: restant.{subdomain}.susankshakya.com.np pattern
4. **Monitoring Stack**: Sentry + Grafana Cloud + Flagsmith
5. **SSL Management**: Let's Encrypt via Dokku

### Service Architecture
```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   GitHub Repo   │───▶│  GitHub Actions  │───▶│  Dokku Server   │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                                        │
                       ┌─────────────────────────────────┼─────────────────────────────────┐
                       │                                 ▼                                 │
                       │        ┌─────────────────────────────────────────┐               │
                       │        │           Laravel Application            │               │
                       │        └─────────────────────────────────────────┘               │
                       │                                 │                                 │
                       ▼                                 ▼                                 ▼
            ┌─────────────────┐              ┌─────────────────┐              ┌─────────────────┐
            │     Sentry      │              │   Flagsmith     │              │  Grafana Cloud  │
            │ Error Tracking  │              │ Feature Flags   │              │   Monitoring    │
            └─────────────────┘              └─────────────────┘              └─────────────────┘
```

## Components and Interfaces

### 1. GitHub Actions Workflow

**File**: `.github/workflows/deploy.yml`

**Responsibilities**:
- Trigger on push to main/staging branches
- Build and test Laravel application
- Compile frontend assets
- Deploy to appropriate Dokku environment
- Run post-deployment tasks

**Key Steps**:
- Checkout code
- Setup PHP 8.1+ and Node.js
- Install dependencies (composer, npm)
- Run tests
- Build production assets
- Deploy via Git push to Dokku
- Run health checks

### 2. Dokku Configuration

**App Structure**:
```
restant-main (production)     → restant.main.susankshakya.com.np
restant-staging (staging)     → restant.staging.susankshakya.com.np
restant-{feature} (feature)   → restant.{feature}.susankshakya.com.np
```

**Services Required**:
- MySQL database
- Redis cache
- Nginx proxy
- Let's Encrypt SSL

### 3. Laravel Application Configuration

**Environment Variables**:
```
# Sentry Configuration
SENTRY_LARAVEL_DSN=https://...
SENTRY_TRACES_SAMPLE_RATE=0.1

# Flagsmith Configuration
FLAGSMITH_ENVIRONMENT_KEY=ser.xxx
FLAGSMITH_API_URL=https://flagsmith.susankshakya.com.np/api/v1/

# Grafana Cloud
GRAFANA_CLOUD_API_KEY=xxx
GRAFANA_CLOUD_INSTANCE_ID=xxx
```

**New Dependencies**:
- `sentry/sentry-laravel`: Error tracking and performance monitoring
- `flagsmith/flagsmith-php-client`: Feature flag management
- Custom Grafana Cloud integration service

### 4. Monitoring Integration

#### Sentry Integration
- **Error Tracking**: Automatic exception capture
- **Performance Monitoring**: Transaction tracing
- **Release Tracking**: Deployment correlation
- **User Context**: Multi-tenant user identification

#### Flagsmith Integration
- **Feature Flags**: Runtime feature control
- **A/B Testing**: Gradual feature rollouts
- **Environment Management**: Per-environment flag states
- **API Integration**: Real-time flag updates

#### Grafana Cloud Integration
- **Application Metrics**: Custom Laravel metrics
- **Infrastructure Monitoring**: Server resource tracking
- **Log Aggregation**: Centralized log management
- **Alerting**: Threshold-based notifications

## Data Models

### Deployment Configuration
```php
// config/deployment.php
return [
    'environments' => [
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
    ],
    'monitoring' => [
        'sentry' => [
            'enabled' => true,
            'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1)
        ],
        'flagsmith' => [
            'enabled' => true,
            'api_url' => env('FLAGSMITH_API_URL')
        ],
        'grafana' => [
            'enabled' => true,
            'instance_id' => env('GRAFANA_CLOUD_INSTANCE_ID')
        ]
    ]
];
```

### Dokku App Configuration
```bash
# Environment variables per app
dokku config:set restant-main \
  APP_ENV=production \
  APP_DEBUG=false \
  SENTRY_LARAVEL_DSN=xxx \
  FLAGSMITH_ENVIRONMENT_KEY=xxx \
  GRAFANA_CLOUD_API_KEY=xxx
```

## Error Handling

### Deployment Failures
1. **Build Failures**: GitHub Actions fails fast on test/build errors
2. **Migration Failures**: Automatic rollback to previous release
3. **Health Check Failures**: Rollback and notification
4. **Service Unavailability**: Graceful degradation with monitoring alerts

### Monitoring Integration Failures
1. **Sentry Unavailable**: Log locally, continue operation
2. **Flagsmith Unavailable**: Use cached/default flag values
3. **Grafana Unavailable**: Buffer metrics, retry with backoff

### Rollback Strategy
```bash
# Automatic rollback on failure
dokku ps:rebuild restant-main  # Rebuild from previous image
dokku domains:add restant-main restant.main.susankshakya.com.np
```

## Testing Strategy

### Pre-deployment Testing
1. **Unit Tests**: PHPUnit test suite execution
2. **Feature Tests**: Laravel feature test coverage
3. **Asset Compilation**: Verify frontend build success
4. **Configuration Validation**: Environment variable checks

### Post-deployment Testing
1. **Health Checks**: HTTP endpoint verification
2. **Database Connectivity**: Migration status verification
3. **Service Integration**: Sentry/Flagsmith/Grafana connectivity
4. **SSL Certificate**: HTTPS functionality verification

### Monitoring Validation
1. **Sentry Integration**: Test error capture and reporting
2. **Flagsmith Connection**: Verify feature flag retrieval
3. **Grafana Metrics**: Confirm metric ingestion
4. **Performance Tracking**: Validate APM data collection

### Rollback Testing
1. **Automated Rollback**: Simulate failure scenarios
2. **Manual Rollback**: Emergency rollback procedures
3. **Data Integrity**: Ensure database consistency
4. **Service Recovery**: Verify service restoration