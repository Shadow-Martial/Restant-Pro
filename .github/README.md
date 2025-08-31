# GitHub Actions Workflows

This directory contains GitHub Actions workflows for automated testing and deployment.

## Workflows

### 1. Deploy to Dokku (`deploy.yml`)

**Triggers:**
- Push to `main` branch (deploys to production)
- Push to `staging` branch (deploys to staging)
- Manual workflow dispatch

**Process:**
1. Checkout code
2. Setup PHP 8.1 and Node.js 18
3. Install dependencies (Composer and NPM)
4. Run PHPUnit tests
5. Build production assets
6. Deploy to Dokku server
7. Run post-deployment tasks
8. Perform health checks
9. Send notifications

**Required Secrets:**
- `DOKKU_SSH_PRIVATE_KEY`: SSH private key for Dokku server access
- `SLACK_WEBHOOK_URL`: (Optional) Slack webhook for notifications

### 2. Run Tests (`test.yml`)

**Triggers:**
- Pull requests to `main` or `staging` branches
- Push to `main` or `staging` branches

**Process:**
1. Setup test environment with MySQL service
2. Install dependencies
3. Run PHPUnit tests with coverage
4. Build assets
5. Upload coverage reports

## Environment Configuration

### Production Environment
- **Branch:** `main`
- **Dokku App:** `restant-main`
- **URL:** `https://restant.main.susankshakya.com.np`

### Staging Environment
- **Branch:** `staging`
- **Dokku App:** `restant-staging`
- **URL:** `https://restant.staging.susankshakya.com.np`

## Setup Instructions

1. **Configure Dokku Server:**
   - Ensure Dokku is installed on server (209.50.227.94)
   - Create apps: `restant-main` and `restant-staging`
   - Configure environment variables for each app

2. **Add GitHub Secrets:**
   ```
   DOKKU_SSH_PRIVATE_KEY=<your-ssh-private-key>
   SLACK_WEBHOOK_URL=<your-slack-webhook-url>
   ```

3. **Test Deployment:**
   - Push to `staging` branch first
   - Verify staging deployment works
   - Then push to `main` for production

## Monitoring

The workflows include:
- Health checks after deployment
- Slack notifications for success/failure
- Coverage reporting
- Deployment logging

## Troubleshooting

Common issues and solutions:

1. **SSH Connection Failed:**
   - Verify SSH key is correctly added to GitHub secrets
   - Ensure Dokku server allows SSH connections

2. **Tests Failing:**
   - Check test database configuration
   - Verify all dependencies are installed

3. **Asset Build Failed:**
   - Check Node.js version compatibility
   - Verify package.json scripts

4. **Health Check Failed:**
   - Check application logs on Dokku server
   - Verify all services are running properly