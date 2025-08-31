# Deployment Troubleshooting Guide

## Overview

This guide provides solutions for common deployment issues encountered with the automated deployment system using GitHub Actions and Dokku.

## Common Deployment Issues

### 1. GitHub Actions Failures

#### Build Failures

**Symptom**: GitHub Actions workflow fails during build phase
```
Error: Process completed with exit code 1
```

**Diagnosis**:
```bash
# Check workflow logs in GitHub Actions tab
# Look for specific error messages in build steps
```

**Solutions**:

1. **Composer Dependencies**:
```bash
# Clear composer cache
composer clear-cache
composer install --no-dev --optimize-autoloader

# Update composer.lock
composer update --lock
```

2. **Node.js Build Issues**:
```bash
# Clear npm cache
npm cache clean --force

# Delete node_modules and reinstall
rm -rf node_modules package-lock.json
npm install
npm run production
```

3. **PHP Version Mismatch**:
```yaml
# Update .github/workflows/deploy.yml
- name: Setup PHP
  uses: shivammathur/setup-php@v2
  with:
    php-version: '8.1'  # Match your production PHP version
```

#### Test Failures

**Symptom**: Tests fail during CI/CD pipeline

**Solutions**:

1. **Database Issues**:
```yaml
# Add to workflow
services:
  mysql:
    image: mysql:8.0
    env:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: testing
    options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
```

2. **Environment Variables**:
```bash
# Add to .env.testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=testing
DB_USERNAME=root
DB_PASSWORD=password
```

### 2. Dokku Deployment Issues

#### SSH Connection Problems

**Symptom**: 
```
Permission denied (publickey)
fatal: Could not read from remote repository
```

**Diagnosis**:
```bash
# Test SSH connection
ssh -T dokku@209.50.227.94

# Check SSH key
ssh-keygen -lf ~/.ssh/id_rsa.pub
```

**Solutions**:

1. **Add SSH Key to Dokku**:
```bash
# On server
cat ~/.ssh/authorized_keys
dokku ssh-keys:list

# Add key if missing
dokku ssh-keys:add github-actions /path/to/public/key
```

2. **GitHub Actions SSH Setup**:
```yaml
- name: Setup SSH
  run: |
    mkdir -p ~/.ssh
    echo "${{ secrets.DOKKU_SSH_PRIVATE_KEY }}" > ~/.ssh/id_rsa
    chmod 600 ~/.ssh/id_rsa
    ssh-keyscan -H 209.50.227.94 >> ~/.ssh/known_hosts
```

#### Git Push Failures

**Symptom**:
```
! [remote rejected] main -> main (pre-receive hook declined)
```

**Diagnosis**:
```bash
# Check Dokku logs
dokku logs restant-main --tail

# Check app status
dokku ps:report restant-main
```

**Solutions**:

1. **Buildpack Issues**:
```bash
# Set correct buildpack
dokku buildpacks:set restant-main heroku/php

# Clear buildpack cache
dokku repo:purge-cache restant-main
```

2. **Memory Issues**:
```bash
# Increase memory limit
dokku resource:limit --memory 1024m restant-main
dokku resource:reserve --memory 512m restant-main
```

### 3. Application Runtime Issues

#### Database Connection Errors

**Symptom**:
```
SQLSTATE[HY000] [2002] Connection refused
```

**Diagnosis**:
```bash
# Check database service
dokku mysql:info restant-db

# Check app database link
dokku mysql:links restant-db
```

**Solutions**:

1. **Link Database Service**:
```bash
dokku mysql:link restant-db restant-main
```

2. **Check Database Credentials**:
```bash
# View database URL
dokku config:get restant-main DATABASE_URL

# Manually set if needed
dokku config:set restant-main DB_HOST=dokku-mysql-restant-db
```

#### Migration Failures

**Symptom**:
```
Migration table not found
Class 'CreateUsersTable' not found
```

**Solutions**:

1. **Run Migrations Manually**:
```bash
dokku run restant-main php artisan migrate --force
dokku run restant-main php artisan db:seed --force
```

2. **Clear Application Cache**:
```bash
dokku run restant-main php artisan config:clear
dokku run restant-main php artisan cache:clear
dokku run restant-main php artisan route:clear
dokku run restant-main php artisan view:clear
```

#### SSL Certificate Issues

**Symptom**:
```
SSL certificate problem: unable to get local issuer certificate
```

**Diagnosis**:
```bash
# Check SSL status
dokku letsencrypt:list

# Test certificate
curl -I https://restant.main.susankshakya.com.np
```

**Solutions**:

1. **Renew Certificate**:
```bash
dokku letsencrypt:enable restant-main
dokku letsencrypt:auto-renew restant-main
```

2. **Check Domain Configuration**:
```bash
dokku domains:report restant-main
dokku proxy:report restant-main
```

### 4. Monitoring Service Issues

#### Sentry Integration Problems

**Symptom**: No errors appearing in Sentry dashboard

**Diagnosis**:
```bash
# Check Sentry configuration
dokku config:get restant-main SENTRY_LARAVEL_DSN

# Test Sentry connection
dokku run restant-main php artisan tinker
>>> \Sentry\captureMessage('Test message');
```

**Solutions**:

1. **Verify DSN Configuration**:
```bash
# Update Sentry DSN
dokku config:set restant-main SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project
```

2. **Check Sentry Service Provider**:
```php
// Ensure in config/app.php
'providers' => [
    Sentry\Laravel\ServiceProvider::class,
],
```

#### Flagsmith Connection Issues

**Symptom**: Feature flags not working

**Diagnosis**:
```bash
# Test Flagsmith connection
dokku run restant-main php artisan tinker
>>> app(\Flagsmith\Flagsmith::class)->getEnvironmentFlags()
```

**Solutions**:

1. **Verify Flagsmith Configuration**:
```bash
dokku config:set restant-main \
  FLAGSMITH_ENVIRONMENT_KEY=ser.your_key \
  FLAGSMITH_API_URL=https://flagsmith.susankshakya.com.np/api/v1/
```

2. **Check Network Connectivity**:
```bash
# Test from container
dokku run restant-main curl -I https://flagsmith.susankshakya.com.np/api/v1/
```

#### Grafana Cloud Issues

**Symptom**: Metrics not appearing in Grafana

**Solutions**:

1. **Verify API Credentials**:
```bash
dokku config:set restant-main \
  GRAFANA_CLOUD_API_KEY=your_api_key \
  GRAFANA_CLOUD_INSTANCE_ID=your_instance_id
```

2. **Test Metrics Endpoint**:
```bash
curl -H "Authorization: Bearer INSTANCE:API_KEY" \
  https://prometheus-prod-01-eu-west-0.grafana.net/api/prom/push
```

## Performance Issues

### Slow Deployments

**Symptoms**: Deployments taking > 10 minutes

**Solutions**:

1. **Optimize Docker Build**:
```dockerfile
# Add to Dockerfile if exists
RUN composer install --no-dev --optimize-autoloader --no-scripts
RUN npm ci --only=production
```

2. **Use Build Cache**:
```yaml
# In GitHub Actions
- name: Cache Composer dependencies
  uses: actions/cache@v3
  with:
    path: vendor
    key: composer-${{ hashFiles('composer.lock') }}
```

### High Memory Usage

**Symptoms**: Application crashes with memory errors

**Solutions**:

1. **Increase Memory Limits**:
```bash
dokku resource:limit --memory 2048m restant-main
dokku config:set restant-main PHP_MEMORY_LIMIT=512M
```

2. **Optimize Application**:
```php
// Add to config/app.php
'debug' => false,

// Optimize caching
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Rollback Procedures

### Automatic Rollback

**When**: Deployment fails health checks

**Process**:
```bash
# Dokku automatically keeps previous releases
dokku ps:rebuild restant-main  # Rebuilds from previous image
```

### Manual Rollback

**When**: Issues discovered after deployment

**Steps**:

1. **Identify Previous Release**:
```bash
dokku releases restant-main
```

2. **Rollback to Specific Release**:
```bash
dokku releases:rollback restant-main <release-number>
```

3. **Verify Rollback**:
```bash
dokku ps:report restant-main
curl -I https://restant.main.susankshakya.com.np
```

### Emergency Rollback

**When**: Critical production issues

**Quick Steps**:
```bash
# Stop current app
dokku ps:stop restant-main

# Rollback to previous
dokku releases:rollback restant-main

# Restart app
dokku ps:start restant-main

# Verify
curl https://restant.main.susankshakya.com.np/health
```

## Debugging Commands

### Application Debugging

```bash
# View application logs
dokku logs restant-main --tail

# Access application shell
dokku run restant-main bash

# Check application status
dokku ps:report restant-main

# View configuration
dokku config restant-main

# Check resource usage
dokku resource:report restant-main
```

### System Debugging

```bash
# Check Dokku status
dokku report

# View system resources
htop
df -h
free -m

# Check Docker containers
docker ps
docker logs <container-id>

# Check network connectivity
netstat -tlnp
ss -tlnp
```

### Database Debugging

```bash
# Connect to database
dokku mysql:connect restant-db

# Check database status
dokku mysql:info restant-db

# View database logs
dokku mysql:logs restant-db

# Backup database
dokku mysql:export restant-db > backup.sql
```

## Monitoring and Alerts

### Health Check Endpoints

Create health check endpoints for monitoring:

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'services' => [
            'database' => DB::connection()->getPdo() ? 'ok' : 'error',
            'cache' => Cache::store()->getStore()->connection()->ping() ? 'ok' : 'error',
        ]
    ]);
});
```

### Log Monitoring

```bash
# Monitor logs in real-time
dokku logs restant-main --tail -f

# Search for errors
dokku logs restant-main | grep -i error

# Monitor specific patterns
dokku logs restant-main | grep -E "(500|error|exception)"
```

## Prevention Strategies

### Pre-deployment Checks

1. **Automated Testing**:
```yaml
# Ensure comprehensive test coverage
- name: Run Tests
  run: |
    php artisan test
    npm run test
```

2. **Code Quality Checks**:
```yaml
- name: Code Quality
  run: |
    ./vendor/bin/phpstan analyse
    ./vendor/bin/php-cs-fixer fix --dry-run
```

### Monitoring Setup

1. **Application Monitoring**:
```bash
# Set up monitoring alerts
dokku checks:set restant-main web /health
```

2. **Resource Monitoring**:
```bash
# Monitor resource usage
dokku resource:limit --memory 1024m --cpu 1000m restant-main
```

## Getting Help

### Log Collection

When reporting issues, collect:

```bash
# Application logs
dokku logs restant-main > app-logs.txt

# System information
dokku report > system-report.txt

# Configuration
dokku config restant-main > app-config.txt
```

### Support Channels

1. **Internal Documentation**: Check operational runbook
2. **Dokku Documentation**: https://dokku.com/docs/
3. **Laravel Documentation**: https://laravel.com/docs/
4. **GitHub Issues**: Create detailed issue reports
5. **Team Communication**: Use established communication channels

### Emergency Contacts

- **DevOps Team**: [Contact Information]
- **System Administrator**: [Contact Information]  
- **On-call Engineer**: [Contact Information]

## Maintenance Tasks

### Regular Maintenance

```bash
# Weekly tasks
dokku cleanup  # Clean up old containers and images
dokku letsencrypt:auto-renew  # Renew SSL certificates

# Monthly tasks
dokku mysql:backup restant-db  # Backup database
dokku logs restant-main --tail 1000 > monthly-logs.txt  # Archive logs
```

### Performance Optimization

```bash
# Optimize application
dokku run restant-main php artisan optimize
dokku run restant-main php artisan config:cache
dokku run restant-main php artisan route:cache
dokku run restant-main php artisan view:cache
```

This troubleshooting guide should be updated regularly based on new issues encountered and solutions discovered.