# Deployment Setup and Configuration Guide

## Overview

This guide provides step-by-step instructions for setting up the automated deployment system for the Laravel multi-tenant SaaS platform using GitHub Actions and Dokku.

## Prerequisites

- Ubuntu 24.04.3 server (IP: 209.50.227.94)
- Domain configured: susankshakya.com.np
- GitHub repository with appropriate access
- SSH access to the server

## Server Setup

### 1. Install Dokku

```bash
# Connect to server
ssh root@209.50.227.94

# Install Dokku
wget https://raw.githubusercontent.com/dokku/dokku/v0.34.0/bootstrap.sh
sudo DOKKU_TAG=v0.34.0 bash bootstrap.sh

# Configure Dokku
dokku domains:set-global susankshakya.com.np
```

### 2. Configure SSL and Domains

```bash
# Install Let's Encrypt plugin
sudo dokku plugin:install https://github.com/dokku/dokku-letsencrypt.git

# Set global email for Let's Encrypt
dokku letsencrypt:set --global email admin@susankshakya.com.np
```

### 3. Install Required Services

```bash
# Install MySQL plugin
sudo dokku plugin:install https://github.com/dokku/dokku-mysql.git mysql

# Install Redis plugin  
sudo dokku plugin:install https://github.com/dokku/dokku-redis.git redis

# Create services
dokku mysql:create restant-db
dokku redis:create restant-cache
```

## Application Deployment Setup

### 1. Create Dokku Applications

```bash
# Create production app
dokku apps:create restant-main
dokku domains:add restant-main restant.main.susankshakya.com.np

# Create staging app
dokku apps:create restant-staging
dokku domains:add restant-staging restant.staging.susankshakya.com.np

# Link services to apps
dokku mysql:link restant-db restant-main
dokku redis:link restant-cache restant-main
dokku mysql:link restant-db restant-staging
dokku redis:link restant-cache restant-staging
```

### 2. Configure Environment Variables

#### Production Environment
```bash
dokku config:set restant-main \
  APP_ENV=production \
  APP_DEBUG=false \
  APP_KEY="base64:$(openssl rand -base64 32)" \
  APP_URL=https://restant.main.susankshakya.com.np \
  SENTRY_LARAVEL_DSN=https://eb01fe83d3662dd65aee15a185d4308c@o4509937918738432.ingest.de.sentry.io/4509938290327632 \
  SENTRY_TRACES_SAMPLE_RATE=0.1 \
  FLAGSMITH_ENVIRONMENT_KEY=ser.XtgjjdGYNq9EdMRXP6gSrX \
  FLAGSMITH_API_URL=https://edge.api.flagsmith.com/api/v1/ \
  GRAFANA_CLOUD_API_KEY=glc_eyJvIjoiMTUyMjU1OSIsIm4iOiJyZXN0YW50LXByby1yZXN0YW50LXBybyIsImsiOiI5VUU1OFZEODhwWVpUQTNjN0E3UWNrMjYiLCJtIjp7InIiOiJ1cyJ9fQ== \
  GRAFANA_CLOUD_INSTANCE_ID=1320535 \
  GRAFANA_LOGS_URL=https://logs-prod-028.grafana.net \
  GRAFANA_LOGS_USER=1320535 \
  GRAFANA_LOGS_PASSWORD=glc_eyJvIjoiMTUyMjU1OSIsIm4iOiJzdGFjay0xMzYyNzQxLWhsLXJlYWQtc3RhY2siLCJrIjoiczhYZzRRdXRGNUYySVhsOTEzTDdaQzE1IiwibSI6eyJyIjoicHJvZC1hcC1zb3V0aC0xIn19
```

#### Staging Environment
```bash
dokku config:set restant-staging \
  APP_ENV=staging \
  APP_DEBUG=true \
  APP_KEY="base64:$(openssl rand -base64 32)" \
  APP_URL=https://restant.staging.susankshakya.com.np \
  SENTRY_LARAVEL_DSN=https://eb01fe83d3662dd65aee15a185d4308c@o4509937918738432.ingest.de.sentry.io/4509938290327632 \
  SENTRY_TRACES_SAMPLE_RATE=0.1 \
  FLAGSMITH_ENVIRONMENT_KEY=ser.XtgjjdGYNq9EdMRXP6gSrX \
  FLAGSMITH_API_URL=https://edge.api.flagsmith.com/api/v1/ \
  GRAFANA_CLOUD_API_KEY=glc_eyJvIjoiMTUyMjU1OSIsIm4iOiJyZXN0YW50LXByby1yZXN0YW50LXBybyIsImsiOiI5VUU1OFZEODhwWVpUQTNjN0E3UWNrMjYiLCJtIjp7InIiOiJ1cyJ9fQ== \
  GRAFANA_CLOUD_INSTANCE_ID=1320535 \
  GRAFANA_LOGS_URL=https://logs-prod-028.grafana.net \
  GRAFANA_LOGS_USER=1320535 \
  GRAFANA_LOGS_PASSWORD=glc_eyJvIjoiMTUyMjU1OSIsIm4iOiJzdGFjay0xMzYyNzQxLWhsLXJlYWQtc3RhY2siLCJrIjoiczhYZzRRdXRGNUYySVhsOTEzTDdaQzE1IiwibSI6eyJyIjoicHJvZC1hcC1zb3V0aC0xIn19
```

### 3. Configure SSL Certificates

```bash
# Enable SSL for production
dokku letsencrypt:enable restant-main

# Enable SSL for staging
dokku letsencrypt:enable restant-staging

# Set up auto-renewal
dokku letsencrypt:cron-job --add
```

## GitHub Actions Setup

### 1. Configure Repository Secrets

In your GitHub repository, go to Settings > Secrets and variables > Actions and add:

```
DOKKU_HOST=209.50.227.94
DOKKU_SSH_PRIVATE_KEY=<your_private_key>
SENTRY_AUTH_TOKEN=<your_sentry_token>
SLACK_WEBHOOK_URL=<your_slack_webhook> (optional)
```

### 2. SSH Key Setup

```bash
# Generate SSH key for GitHub Actions
ssh-keygen -t ed25519 -C "github-actions@yourdomain.com" -f ~/.ssh/github_actions

# Add public key to Dokku
cat ~/.ssh/github_actions.pub | dokku ssh-keys:add github-actions

# Copy private key content to GitHub secrets as DOKKU_SSH_PRIVATE_KEY
cat ~/.ssh/github_actions
```

### 3. Verify Workflow Files

Ensure these files exist in your repository:
- `.github/workflows/deploy.yml` - Main deployment workflow
- `scripts/dokku-setup.sh` - Dokku configuration script
- `scripts/deploy.sh` - Deployment script

## Laravel Application Configuration

### 1. Update Composer Dependencies

Add to `composer.json`:
```json
{
  "require": {
    "sentry/sentry-laravel": "^4.0",
    "flagsmith/flagsmith-php-client": "^2.0"
  }
}
```

### 2. Configure Service Providers

Add to `config/app.php`:
```php
'providers' => [
    // Other providers...
    Sentry\Laravel\ServiceProvider::class,
    App\Providers\FlagsmithServiceProvider::class,
    App\Providers\GrafanaServiceProvider::class,
],
```

### 3. Publish Configuration Files

```bash
php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
php artisan config:cache
```

### 4. Testing Configuration

For testing environments, the following services are configured:

- **Sentry**: Enabled with production DSN for error tracking during tests
- **Flagsmith**: Disabled in test environment to avoid external API calls
- **Grafana**: Disabled in test environment to avoid metrics collection during tests

Test configuration is managed in:
- `phpunit.deployment.xml` - PHPUnit configuration for deployment tests
- `tests/DeploymentTestRunner.php` - Test orchestration and environment setup

## Verification Steps

### 1. Test Deployment

```bash
# Push to trigger deployment
git push origin main

# Check deployment status
dokku ps:report restant-main
```

### 2. Verify Services

```bash
# Check application logs
dokku logs restant-main --tail

# Verify SSL certificate
curl -I https://restant.main.susankshakya.com.np

# Test database connection
dokku run restant-main php artisan migrate:status
```

### 3. Monitor Integration

- Check Sentry dashboard for error tracking
- Verify Flagsmith connection in application
- Confirm Grafana Cloud metrics ingestion

## Security Considerations

1. **Environment Variables**: Never commit sensitive data to repository
2. **SSH Keys**: Use dedicated keys for deployment with minimal permissions
3. **SSL Certificates**: Ensure auto-renewal is configured
4. **Database Access**: Restrict database access to application only
5. **Monitoring**: Set up alerts for failed deployments and security issues

## Next Steps

1. Configure monitoring service integrations (see monitoring-integration-guide.md)
2. Set up deployment notifications
3. Test rollback procedures
4. Configure backup strategies
5. Set up log rotation and cleanup

## Support

For issues with this setup, refer to:
- Troubleshooting guide: `docs/deployment-troubleshooting.md`
- Operational runbook: `docs/deployment-operations.md`
- Dokku documentation: https://dokku.com/docs/