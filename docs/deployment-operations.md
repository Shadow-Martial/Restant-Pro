# Deployment Operations Runbook

## Overview

This operational runbook provides procedures for managing the automated deployment system, including routine operations, emergency procedures, and maintenance tasks.

## Daily Operations

### Morning Checklist

1. **System Health Check**
```bash
# Check application status
dokku ps:report restant-main
dokku ps:report restant-staging

# Verify SSL certificates
dokku letsencrypt:list

# Check resource usage
dokku resource:report restant-main
dokku resource:report restant-staging
```

2. **Monitor Deployment Status**
```bash
# Check recent deployments
git log --oneline -10
dokku releases restant-main | head -5

# Review deployment logs
dokku logs restant-main --tail 100 | grep -E "(deploy|error|warning)"
```

3. **Service Health Verification**
```bash
# Test application endpoints
curl -I https://restant.main.susankshakya.com.np/health
curl -I https://restant.staging.susankshakya.com.np/health

# Check database connectivity
dokku mysql:info restant-db
dokku redis:info restant-cache
```

### Monitoring Dashboard Review

1. **Sentry Dashboard**
   - Review error rates and new issues
   - Check performance metrics
   - Verify release tracking

2. **Grafana Cloud**
   - Monitor application metrics
   - Check infrastructure health
   - Review alert status

3. **Flagsmith Console**
   - Verify feature flag status
   - Review flag usage statistics
   - Check environment synchronization

## Deployment Procedures

### Standard Deployment

#### Production Deployment
```bash
# 1. Verify staging deployment
curl -I https://restant.staging.susankshakya.com.np/health

# 2. Create production deployment
git checkout main
git pull origin main
git push origin main  # Triggers GitHub Actions

# 3. Monitor deployment progress
# Check GitHub Actions: https://github.com/your-repo/actions

# 4. Verify deployment
curl -I https://restant.main.susankshakya.com.np/health
dokku logs restant-main --tail 50

# 5. Post-deployment checks
dokku run restant-main php artisan migrate:status
dokku run restant-main php artisan queue:work --once --timeout=30
```

#### Staging Deployment
```bash
# 1. Push to staging branch
git checkout staging
git merge main
git push origin staging

# 2. Monitor and verify
curl -I https://restant.staging.susankshakya.com.np/health
```

### Feature Branch Deployment

```bash
# 1. Create feature app
dokku apps:create restant-feature-xyz
dokku domains:add restant-feature-xyz restant.feature-xyz.susankshakya.com.np

# 2. Link services
dokku mysql:link restant-db restant-feature-xyz
dokku redis:link restant-cache restant-feature-xyz

# 3. Configure environment
dokku config:set restant-feature-xyz \
  APP_ENV=staging \
  APP_DEBUG=true \
  APP_URL=https://restant.feature-xyz.susankshakya.com.np

# 4. Deploy feature branch
git push dokku@209.50.227.94:restant-feature-xyz feature-branch:main

# 5. Enable SSL
dokku letsencrypt:enable restant-feature-xyz
```

### Hotfix Deployment

**Emergency hotfix procedure for critical production issues:**

```bash
# 1. Create hotfix branch
git checkout main
git checkout -b hotfix/critical-fix

# 2. Make minimal changes
# Edit files as needed

# 3. Test locally
php artisan test
npm run test

# 4. Commit and push
git add .
git commit -m "hotfix: critical production issue"
git push origin hotfix/critical-fix

# 5. Deploy directly to production (bypass staging)
git checkout main
git merge hotfix/critical-fix
git push origin main

# 6. Monitor deployment closely
dokku logs restant-main --tail -f

# 7. Verify fix
curl -I https://restant.main.susankshakya.com.np/health
# Test specific functionality that was fixed

# 8. Update staging
git checkout staging
git merge main
git push origin staging
```

## Rollback Procedures

### Automatic Rollback

**Triggered when deployment fails health checks:**

```bash
# Monitor automatic rollback
dokku logs restant-main --tail -f

# Verify rollback completion
dokku releases restant-main
curl -I https://restant.main.susankshakya.com.np/health
```

### Manual Rollback

**When issues are discovered after successful deployment:**

```bash
# 1. Identify target release
dokku releases restant-main

# 2. Perform rollback
dokku releases:rollback restant-main <release-number>

# 3. Verify rollback
dokku ps:report restant-main
curl -I https://restant.main.susankshakya.com.np/health

# 4. Notify team
# Send notification about rollback and reason

# 5. Update monitoring
# Add incident to Sentry/monitoring systems
```

### Emergency Rollback

**For critical production issues requiring immediate action:**

```bash
# 1. Stop current application
dokku ps:stop restant-main

# 2. Quick rollback to last known good release
dokku releases:rollback restant-main 1

# 3. Start application
dokku ps:start restant-main

# 4. Immediate verification
curl https://restant.main.susankshakya.com.np/health

# 5. Emergency notification
# Alert all stakeholders immediately

# 6. Post-incident analysis
# Schedule post-mortem meeting
```

## Maintenance Operations

### Weekly Maintenance

**Every Monday at 9:00 AM:**

```bash
# 1. System cleanup
dokku cleanup

# 2. SSL certificate renewal check
dokku letsencrypt:auto-renew

# 3. Database maintenance
dokku mysql:backup restant-db
dokku mysql:backup-schedule restant-db "0 2 * * *"  # Daily at 2 AM

# 4. Log rotation
dokku logs restant-main --tail 10000 > logs/weekly-$(date +%Y%m%d).log
dokku logs restant-staging --tail 10000 > logs/staging-weekly-$(date +%Y%m%d).log

# 5. Resource usage review
dokku resource:report restant-main
dokku resource:report restant-staging

# 6. Security updates
apt update && apt list --upgradable
# Schedule security updates during maintenance window
```

### Monthly Maintenance

**First Saturday of each month:**

```bash
# 1. Full system backup
dokku mysql:export restant-db > backups/monthly-$(date +%Y%m).sql
tar -czf backups/app-$(date +%Y%m).tar.gz /home/dokku/restant-main

# 2. Performance optimization
dokku run restant-main php artisan optimize
dokku run restant-main php artisan config:cache
dokku run restant-main php artisan route:cache
dokku run restant-main php artisan view:cache

# 3. Dependency updates (staging first)
# Update composer.json and package.json
# Test in staging environment
# Deploy to production after verification

# 4. Security audit
dokku run restant-main composer audit
npm audit

# 5. Monitoring review
# Review Sentry error trends
# Analyze Grafana performance metrics
# Update alerting thresholds if needed
```

### Quarterly Maintenance

**Every 3 months:**

```bash
# 1. Infrastructure review
# Review server resources and scaling needs
# Evaluate monitoring and alerting effectiveness
# Update disaster recovery procedures

# 2. Security hardening
# Review SSL certificate configurations
# Update firewall rules
# Audit user access and permissions

# 3. Documentation updates
# Update this runbook with new procedures
# Review and update troubleshooting guide
# Update team contact information

# 4. Disaster recovery testing
# Test backup restoration procedures
# Verify rollback mechanisms
# Test emergency contact procedures
```

## Monitoring and Alerting

### Key Metrics to Monitor

1. **Application Health**
   - Response time < 2 seconds
   - Error rate < 1%
   - Uptime > 99.9%

2. **Infrastructure Health**
   - CPU usage < 80%
   - Memory usage < 85%
   - Disk usage < 90%

3. **Database Performance**
   - Connection pool utilization < 80%
   - Query response time < 500ms
   - Replication lag < 1 second

### Alert Thresholds

#### Critical Alerts (Immediate Response)
```bash
# Application down
curl -f https://restant.main.susankshakya.com.np/health || echo "CRITICAL: Application down"

# High error rate (>5% in 5 minutes)
# Database connection failures
# SSL certificate expiration (< 7 days)
```

#### Warning Alerts (Response within 1 hour)
```bash
# High response time (>3 seconds average)
# Memory usage >85%
# Disk usage >85%
# Failed deployment
```

#### Info Alerts (Response within 24 hours)
```bash
# Successful deployment
# SSL certificate renewal
# Scheduled maintenance completion
```

### Alert Response Procedures

#### Critical Alert Response
1. **Acknowledge alert** within 5 minutes
2. **Assess impact** and determine if rollback is needed
3. **Execute emergency procedures** if necessary
4. **Communicate status** to stakeholders
5. **Implement fix** or rollback
6. **Verify resolution** and close alert
7. **Document incident** for post-mortem

#### Warning Alert Response
1. **Acknowledge alert** within 1 hour
2. **Investigate root cause**
3. **Plan remediation** during next maintenance window
4. **Monitor for escalation** to critical
5. **Implement fix** when appropriate
6. **Update monitoring** if needed

## Security Operations

### Security Monitoring

```bash
# Daily security checks
# Check for failed login attempts
dokku logs restant-main | grep -i "authentication failed"

# Monitor for suspicious activity
dokku logs restant-main | grep -E "(sql injection|xss|csrf)"

# Check SSL certificate status
dokku letsencrypt:list
```

### Security Incident Response

1. **Immediate Actions**
   - Isolate affected systems
   - Preserve evidence
   - Assess scope of breach

2. **Investigation**
   - Review logs for attack vectors
   - Identify compromised data
   - Document timeline of events

3. **Containment**
   - Block malicious IPs
   - Rotate compromised credentials
   - Apply security patches

4. **Recovery**
   - Restore from clean backups
   - Verify system integrity
   - Resume normal operations

5. **Post-Incident**
   - Conduct security review
   - Update security procedures
   - Implement additional controls

## Disaster Recovery

### Backup Procedures

#### Database Backups
```bash
# Daily automated backup
dokku mysql:backup restant-db

# Manual backup before major changes
dokku mysql:export restant-db > backup-$(date +%Y%m%d-%H%M).sql

# Verify backup integrity
dokku mysql:import restant-db-test < backup-file.sql
```

#### Application Backups
```bash
# Code backup (Git repository)
git clone --mirror https://github.com/your-repo/app.git backup-repo.git

# Configuration backup
dokku config restant-main > config-backup-$(date +%Y%m%d).txt

# File uploads backup
rsync -av /var/lib/dokku/data/storage/restant-main/ backup/uploads/
```

### Recovery Procedures

#### Database Recovery
```bash
# 1. Stop application
dokku ps:stop restant-main

# 2. Restore database
dokku mysql:import restant-db < backup-file.sql

# 3. Verify data integrity
dokku mysql:connect restant-db
# Run verification queries

# 4. Start application
dokku ps:start restant-main

# 5. Verify application functionality
curl -I https://restant.main.susankshakya.com.np/health
```

#### Full System Recovery
```bash
# 1. Provision new server
# Follow deployment-setup-guide.md

# 2. Restore database
dokku mysql:import restant-db < latest-backup.sql

# 3. Deploy application
git push dokku@new-server:restant-main main

# 4. Restore file uploads
rsync -av backup/uploads/ /var/lib/dokku/data/storage/restant-main/

# 5. Update DNS records
# Point domain to new server

# 6. Verify full functionality
# Run comprehensive tests
```

## Team Communication

### Deployment Notifications

#### Successful Deployment
```
✅ Deployment Successful
Environment: Production
Version: v1.2.3
Deployed by: [Developer Name]
Time: [Timestamp]
Changes: [Brief description]
Health Check: ✅ Passed
```

#### Failed Deployment
```
❌ Deployment Failed
Environment: Production
Version: v1.2.3
Error: [Error description]
Action Required: [Next steps]
Rollback Status: [Automatic/Manual/Pending]
```

#### Emergency Alert
```
🚨 EMERGENCY: Production Issue
Issue: [Description]
Impact: [User impact description]
Status: [Investigating/Fixing/Resolved]
ETA: [Estimated resolution time]
Updates: [Where to get updates]
```

### Escalation Procedures

#### Level 1: Development Team
- Response time: 15 minutes during business hours
- Scope: Application issues, minor performance problems
- Contact: Team Slack channel

#### Level 2: DevOps Team
- Response time: 30 minutes during business hours, 1 hour off-hours
- Scope: Infrastructure issues, deployment failures
- Contact: DevOps on-call rotation

#### Level 3: Management
- Response time: 1 hour
- Scope: Critical business impact, security incidents
- Contact: Engineering manager, CTO

### Communication Channels

1. **Slack Channels**
   - #deployments: All deployment notifications
   - #alerts: Monitoring alerts and system issues
   - #incidents: Active incident coordination

2. **Email Lists**
   - deployments@company.com: Deployment summaries
   - alerts@company.com: Critical system alerts
   - oncall@company.com: Emergency escalation

3. **Status Page**
   - Update public status page for user-facing issues
   - Include estimated resolution times
   - Provide regular updates during incidents

## Performance Optimization

### Application Performance

```bash
# Enable OPcache
dokku config:set restant-main PHP_OPCACHE_ENABLE=1

# Optimize Laravel caching
dokku run restant-main php artisan config:cache
dokku run restant-main php artisan route:cache
dokku run restant-main php artisan view:cache

# Database query optimization
dokku run restant-main php artisan telescope:clear
# Review slow queries in monitoring
```

### Infrastructure Optimization

```bash
# Monitor resource usage
dokku resource:report restant-main

# Adjust resource limits based on usage
dokku resource:limit --memory 2048m --cpu 2000m restant-main

# Enable HTTP/2 and compression
dokku nginx:set restant-main hsts true
dokku nginx:set restant-main gzip true
```

## Compliance and Auditing

### Audit Trail

```bash
# Deployment history
dokku releases restant-main

# Configuration changes
git log --oneline config/

# Access logs
dokku logs restant-main | grep -E "(login|logout|admin)"
```

### Compliance Checks

1. **Data Protection**
   - Verify encryption at rest and in transit
   - Check data retention policies
   - Audit user access controls

2. **Security Standards**
   - SSL/TLS configuration review
   - Vulnerability scanning results
   - Security patch compliance

3. **Operational Standards**
   - Backup verification
   - Disaster recovery testing
   - Change management compliance

## Continuous Improvement

### Monthly Review

1. **Performance Analysis**
   - Review deployment frequency and success rate
   - Analyze mean time to recovery (MTTR)
   - Identify bottlenecks and optimization opportunities

2. **Process Improvement**
   - Update procedures based on lessons learned
   - Automate repetitive tasks
   - Enhance monitoring and alerting

3. **Team Training**
   - Conduct runbook reviews with team
   - Share knowledge from incidents
   - Update emergency contact information

### Quarterly Planning

1. **Infrastructure Planning**
   - Capacity planning based on growth
   - Technology stack evaluation
   - Security enhancement roadmap

2. **Process Enhancement**
   - Deployment pipeline improvements
   - Monitoring and observability enhancements
   - Disaster recovery procedure updates

This operational runbook should be reviewed and updated monthly to ensure it remains current and effective.