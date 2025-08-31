# Deployment Guide

## Overview

This project uses GitHub Actions for automated deployment to a Dokku server. The deployment process is triggered automatically when code is pushed to the `main` or `staging` branches.

## Prerequisites

### GitHub Secrets

The following secrets must be configured in your GitHub repository:

- `DOKKU_SSH_PRIVATE_KEY`: SSH private key for connecting to the Dokku server
- `SLACK_WEBHOOK_URL`: (Optional) Slack webhook URL for deployment notifications

### Dokku Server Setup

The Dokku server (209.50.227.94) should have the following apps configured:

- `restant-main`: Production environment (main branch)
- `restant-staging`: Staging environment (staging branch)

## Deployment Process

### Automatic Deployment

1. Push code to `main` or `staging` branch
2. GitHub Actions workflow triggers automatically
3. Code is tested and built
4. Application is deployed to the appropriate Dokku app
5. Post-deployment tasks are executed
6. Health checks are performed
7. Notifications are sent

### Manual Deployment

You can also trigger deployments manually:

1. Go to the Actions tab in your GitHub repository
2. Select the "Deploy to Dokku" workflow
3. Click "Run workflow"
4. Choose the branch to deploy

## Environment Configuration

### Production (main branch)
- App: `restant-main`
- URL: `https://restant.main.susankshakya.com.np`
- Environment: `production`

### Staging (staging branch)
- App: `restant-staging`
- URL: `https://restant.staging.susankshakya.com.np`
- Environment: `staging`

## Health Checks

The deployment process includes health checks to verify:

- Application is responding
- Database connectivity
- Cache functionality
- External service integrations (Sentry, Flagsmith, Grafana)

## Rollback

If a deployment fails, the system will automatically attempt to rollback to the previous version. Manual rollback can be performed using Dokku commands:

```bash
# SSH into the Dokku server
ssh dokku@209.50.227.94

# Rollback to previous release
dokku ps:rebuild restant-main
```

## Troubleshooting

### Common Issues

1. **SSH Connection Failed**: Verify the `DOKKU_SSH_PRIVATE_KEY` secret is correctly configured
2. **Health Check Failed**: Check application logs and ensure all services are running
3. **Migration Failed**: Review database migration files for syntax errors
4. **Asset Build Failed**: Check Node.js dependencies and build scripts

### Viewing Logs

```bash
# View application logs
dokku logs restant-main

# View deployment logs
dokku logs restant-main --tail
```

## Monitoring

The deployment integrates with:

- **Sentry**: Error tracking and performance monitoring
- **Flagsmith**: Feature flag management
- **Grafana Cloud**: Infrastructure and application metrics

All services include health checks to ensure proper integration.