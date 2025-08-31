# Dokku Setup Scripts

This directory contains scripts for setting up and managing Dokku applications for the Laravel multi-tenant SaaS platform.

## Prerequisites

Before running these scripts, ensure you have:

1. **Dokku installed** on Ubuntu 24.04.3 server (209.50.227.94)
2. **Required Dokku plugins** installed:
   ```bash
   # MySQL plugin
   dokku plugin:install https://github.com/dokku/dokku-mysql.git mysql
   
   # Redis plugin
   dokku plugin:install https://github.com/dokku/dokku-redis.git redis
   
   # Let's Encrypt plugin
   dokku plugin:install https://github.com/dokku/dokku-letsencrypt.git
   ```
3. **DNS configured** for `*.susankshakya.com.np` pointing to your server
4. **SSH access** to the Dokku server

## Scripts Overview

### 1. `dokku-setup.sh` - Main Setup Script

Sets up the complete Dokku environment with apps and services.

**Usage:**
```bash
# Run on Dokku server
./dokku-setup.sh
```

**What it does:**
- Creates MySQL service (`mysql-restant`)
- Creates Redis service (`redis-restant`)
- Creates production app (`restant-main`) → `restant.main.susankshakya.com.np`
- Creates staging app (`restant-staging`) → `restant.staging.susankshakya.com.np`
- Links services to apps
- Configures SSL certificates
- Sets basic Laravel environment variables

### 2. `dokku-app-config.sh` - App Configuration

Configures individual Dokku apps with environment-specific settings.

**Usage:**
```bash
# Configure production app
./dokku-app-config.sh restant-main production \
  --app-url https://restant.main.susankshakya.com.np \
  --sentry-dsn "https://xxx@sentry.io/xxx" \
  --flagsmith-key "ser.xxx" \
  --flagsmith-url "https://flagsmith.susankshakya.com.np/api/v1/" \
  --grafana-key "xxx" \
  --grafana-instance "xxx"

# Configure staging app
./dokku-app-config.sh restant-staging staging \
  --app-url https://restant.staging.susankshakya.com.np
```

**Options:**
- `--sentry-dsn <dsn>` - Sentry DSN for error tracking
- `--flagsmith-key <key>` - Flagsmith environment key
- `--flagsmith-url <url>` - Flagsmith API URL
- `--grafana-key <key>` - Grafana Cloud API key
- `--grafana-instance <id>` - Grafana Cloud instance ID
- `--app-url <url>` - Application URL

### 3. `dokku-ssl-setup.sh` - SSL Management

Manages SSL certificates using Let's Encrypt.

**Usage:**
```bash
# Setup SSL for specific app
./dokku-ssl-setup.sh setup restant-main --email admin@susankshakya.com.np

# Setup SSL for all apps
./dokku-ssl-setup.sh setup-all --email admin@susankshakya.com.np

# Check SSL status
./dokku-ssl-setup.sh status restant-main

# Renew SSL certificate
./dokku-ssl-setup.sh renew restant-main

# Enable automatic renewal
./dokku-ssl-setup.sh enable-cron
```

### 4. `dokku-services-setup.sh` - Service Management

Manages MySQL and Redis services.

**Usage:**
```bash
# Setup all services
./dokku-services-setup.sh setup-all

# Create individual services
./dokku-services-setup.sh create-mysql mysql-restant
./dokku-services-setup.sh create-redis redis-restant

# Link services to apps
./dokku-services-setup.sh link-mysql mysql-restant restant-main
./dokku-services-setup.sh link-redis redis-restant restant-main

# Backup MySQL
./dokku-services-setup.sh backup-mysql mysql-restant

# Check service status
./dokku-services-setup.sh status-mysql mysql-restant
./dokku-services-setup.sh list-services
```

## Complete Setup Process

### Step 1: Initial Server Setup

1. **Install Dokku** (if not already installed):
   ```bash
   wget https://raw.githubusercontent.com/dokku/dokku/v0.32.4/bootstrap.sh
   sudo DOKKU_TAG=v0.32.4 bash bootstrap.sh
   ```

2. **Install required plugins**:
   ```bash
   dokku plugin:install https://github.com/dokku/dokku-mysql.git mysql
   dokku plugin:install https://github.com/dokku/dokku-redis.git redis
   dokku plugin:install https://github.com/dokku/dokku-letsencrypt.git
   ```

### Step 2: Run Setup Scripts

1. **Copy scripts to server**:
   ```bash
   scp scripts/*.sh user@209.50.227.94:/home/user/
   ```

2. **Make scripts executable**:
   ```bash
   chmod +x *.sh
   ```

3. **Run main setup**:
   ```bash
   ./dokku-setup.sh
   ```

4. **Configure apps with monitoring**:
   ```bash
   # Production
   ./dokku-app-config.sh restant-main production \
     --app-url https://restant.main.susankshakya.com.np \
     --sentry-dsn "YOUR_SENTRY_DSN" \
     --flagsmith-key "YOUR_FLAGSMITH_KEY"
   
   # Staging
   ./dokku-app-config.sh restant-staging staging \
     --app-url https://restant.staging.susankshakya.com.np
   ```

### Step 3: Deploy Application

1. **Add Dokku remote to your Git repository**:
   ```bash
   # For production
   git remote add dokku-main dokku@209.50.227.94:restant-main
   
   # For staging
   git remote add dokku-staging dokku@209.50.227.94:restant-staging
   ```

2. **Deploy**:
   ```bash
   # Deploy to production
   git push dokku-main main:main
   
   # Deploy to staging
   git push dokku-staging staging:main
   ```

## Environment Variables

The scripts automatically configure these Laravel environment variables:

### Basic Laravel Settings
- `APP_ENV` - Environment (production/staging)
- `APP_DEBUG` - Debug mode (false for production)
- `APP_KEY` - Application key (auto-generated)
- `APP_URL` - Application URL

### Database & Cache
- `DATABASE_URL` - MySQL connection (auto-configured)
- `REDIS_URL` - Redis connection (auto-configured)
- `CACHE_DRIVER=redis`
- `SESSION_DRIVER=redis`
- `QUEUE_CONNECTION=redis`

### Monitoring (if configured)
- `SENTRY_LARAVEL_DSN` - Sentry error tracking
- `SENTRY_TRACES_SAMPLE_RATE` - Performance monitoring
- `FLAGSMITH_ENVIRONMENT_KEY` - Feature flags
- `FLAGSMITH_API_URL` - Flagsmith API endpoint
- `GRAFANA_CLOUD_API_KEY` - Grafana Cloud metrics
- `GRAFANA_CLOUD_INSTANCE_ID` - Grafana instance

## Troubleshooting

### Common Issues

1. **SSL Certificate Fails**:
   ```bash
   # Check domain configuration
   dokku domains:report restant-main
   
   # Verify DNS resolution
   nslookup restant.main.susankshakya.com.np
   
   # Force SSL setup
   ./dokku-ssl-setup.sh setup restant-main --force
   ```

2. **Service Connection Issues**:
   ```bash
   # Check service status
   ./dokku-services-setup.sh status-mysql mysql-restant
   ./dokku-services-setup.sh status-redis redis-restant
   
   # Restart services
   dokku mysql:restart mysql-restant
   dokku redis:restart redis-restant
   ```

3. **App Deployment Fails**:
   ```bash
   # Check app logs
   dokku logs restant-main --tail
   
   # Check configuration
   dokku config restant-main
   
   # Rebuild app
   dokku ps:rebuild restant-main
   ```

### Useful Commands

```bash
# List all apps
dokku apps:list

# List all services
dokku mysql:list
dokku redis:list

# Check app status
dokku ps:report restant-main

# View app configuration
dokku config restant-main

# Check SSL certificates
dokku letsencrypt:list

# View app logs
dokku logs restant-main --tail
```

## Security Notes

1. **Environment Variables**: Sensitive data is stored securely in Dokku's config system
2. **SSL Certificates**: Automatically managed by Let's Encrypt
3. **Database Access**: Services are only accessible within the Dokku network
4. **Backups**: Regular MySQL backups should be scheduled using the backup script

## Maintenance

### Regular Tasks

1. **SSL Certificate Renewal**: Automated via cron job
2. **Database Backups**: 
   ```bash
   # Weekly backup
   ./dokku-services-setup.sh backup-mysql mysql-restant
   ```
3. **Service Updates**:
   ```bash
   # Update service images
   dokku mysql:upgrade mysql-restant
   dokku redis:upgrade redis-restant
   ```

### Monitoring

- Check application logs regularly: `dokku logs restant-main --tail`
- Monitor SSL certificate expiration: `./dokku-ssl-setup.sh status restant-main`
- Verify service health: `./dokku-services-setup.sh list-services`