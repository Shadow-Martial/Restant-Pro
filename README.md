# Restant - Multi-Tenant SaaS Platform

A comprehensive Laravel-based multi-tenant SaaS platform with automated deployment, monitoring, and rollback capabilities.

## 🚀 Automated Deployment System

This project features a complete automated deployment pipeline with:

- **GitHub Actions CI/CD** - Automated testing and deployment workflows
- **Dokku Integration** - Seamless deployment to production and staging environments  
- **Multi-Environment Support** - Production (`restant.main.susankshakya.com.np`) and Staging (`restant.staging.susankshakya.com.np`)
- **Zero-Downtime Deployments** - Blue-green and canary deployment strategies
- **Automatic Rollback** - Intelligent failure detection and automatic rollback capabilities
- **Comprehensive Monitoring** - Integrated with Sentry, Flagsmith, and Grafana Cloud

## 📊 Monitoring & Observability

### Sentry Integration
- **Error Tracking**: Real-time error monitoring and alerting
- **Performance Monitoring**: Application performance insights and traces
- **DSN**: `https://eb01fe83d3662dd65aee15a185d4308c@o4509937918738432.ingest.de.sentry.io/4509938290327632`

### Flagsmith Feature Flags
- **Feature Management**: Dynamic feature flag control
- **Environment Key**: `ser.XtgjjdGYNq9EdMRXP6gSrX`
- **API URL**: `https://edge.api.flagsmith.com/api/v1/`

### Grafana Cloud Monitoring
- **Logs**: Centralized log aggregation via Loki (`https://logs-prod-028.grafana.net`)
- **Metrics**: Application and infrastructure metrics via Prometheus
- **Instance ID**: `1320535`
- **Dashboards**: Real-time monitoring and alerting

## 🧪 Comprehensive Testing Suite

The project includes a robust testing framework:

```bash
# Run all deployment tests
php artisan deployment:test

# Run specific test suites
php artisan deployment:test --suite=unit
php artisan deployment:test --suite=integration
php artisan deployment:test --suite=feature

# Run with detailed reporting
php artisan deployment:test --report --coverage --verbose
```

### Test Coverage
- **Unit Tests**: Configuration validation and service testing
- **Integration Tests**: Monitoring services and workflow integration
- **Feature Tests**: End-to-end deployment scenarios and rollback testing
- **Scenario Tests**: Zero-downtime, canary, and blue-green deployments

## 🔧 Quick Start

### Prerequisites
- PHP 8.1+
- Laravel 10+
- MySQL 8.0+
- Redis 7.0+
- Node.js 18+

### Installation
```bash
# Clone repository
git clone <repository-url>
cd restant

# Install dependencies
composer install
npm install

# Configure environment
cp .env.production.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Build assets
npm run production
```

### Deployment
```bash
# Deploy to staging
git push origin staging

# Deploy to production
git push origin main
```

## 📚 Documentation

- **[Deployment Setup Guide](docs/deployment-setup-guide.md)** - Complete deployment configuration
- **[Sentry Integration](docs/sentry-integration.md)** - Error monitoring setup
- **[Testing Guide](tests/README.md)** - Comprehensive testing documentation
- **[Operations Runbook](docs/deployment-operations.md)** - Operational procedures

## 🏗️ Architecture

### Deployment Pipeline
1. **Code Push** → GitHub Actions triggered
2. **Testing** → Automated test suite execution
3. **Build** → Asset compilation and optimization
4. **Deploy** → Dokku deployment with health checks
5. **Monitor** → Real-time monitoring and alerting
6. **Rollback** → Automatic rollback on failure detection

### Infrastructure
- **Server**: Ubuntu 24.04.3 (209.50.227.94)
- **Platform**: Dokku PaaS
- **Database**: MySQL with automated backups
- **Cache**: Redis for session and application caching
- **SSL**: Let's Encrypt with auto-renewal


## Test
php artisan test --testsuite=Feature

## License

The Laravel framework is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## ENV
SHOW_DEMO_CREDENTIALS=true
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=laravel


MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS='test@example.com'
MAIL_FROM_NAME='App Demo'

## Updates

git diff --name-only 07f20373480c2237d3e5a743aca217089afeee02 > .diff-files.txt && npm run zipupdate

COMPOSER_MEMORY_LIMIT=-1 composer require */**

## Clearing cache
php artisan cache:clear
ddcache
php artisan config:cache
php artisan config:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache
php artisan optimize

## Create new module
php artisan module:make Fields
php artisan module:make-migration create_fields_table fields
https://github.com/akaunting/laravel-module

## Zip withoit mac
zip -r es_lang.zip . -x ".*" -x "__MACOSX"

## Sync missing keys
php artisan translation:sync-missing-translation-keys


## Default .env
[.env](https://paste.laravel.io/2fe670c7-f66b-443e-9e79-b5fa6618360b)