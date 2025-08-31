# Deployment Notification System

This document describes the deployment notification system that sends notifications for deployment events across multiple channels.

## Overview

The deployment notification system provides real-time notifications for:
- Deployment started events
- Deployment success/failure events  
- Rollback events

## Supported Channels

### Slack
Send notifications to Slack channels via webhooks.

**Configuration:**
```env
DEPLOYMENT_SLACK_ENABLED=true
DEPLOYMENT_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/SLACK/WEBHOOK
```

### Email
Send HTML and text email notifications to team members.

**Configuration:**
```env
DEPLOYMENT_EMAIL_ENABLED=true
DEPLOYMENT_EMAIL_RECIPIENTS=dev-team@example.com,ops@example.com
```

### Webhook
Send JSON payloads to custom webhook endpoints.

**Configuration:**
```env
DEPLOYMENT_WEBHOOK_ENABLED=true
DEPLOYMENT_WEBHOOK_URL=https://your-webhook-endpoint.com/deployment
DEPLOYMENT_WEBHOOK_AUTH_HEADER="Bearer your-auth-token"
```

## Usage

### Direct Service Usage

```php
use App\Services\DeploymentNotificationService;

$notificationService = app(DeploymentNotificationService::class);

// Notify deployment started
$notificationService->notifyDeploymentStarted('production', 'main', 'abc123');

// Notify deployment success
$notificationService->notifyDeploymentSuccess('production', 'main', 'abc123', [
    'duration' => '2m 30s',
    'migrations_run' => 3
]);

// Notify deployment failure
$notificationService->notifyDeploymentFailure('production', 'main', 'abc123', 'Migration failed');

// Notify rollback
$notificationService->notifyRollback('production', 'Health check failed', 'def456');
```

### Using Facade

```php
use App\Facades\DeploymentNotification;

DeploymentNotification::notifyDeploymentStarted('production', 'main', 'abc123');
```

### Using Events

```php
use App\Events\DeploymentStarted;
use App\Events\DeploymentCompleted;
use App\Events\DeploymentRollback;

// Fire events that automatically trigger notifications
event(new DeploymentStarted('production', 'main', 'abc123'));
event(new DeploymentCompleted('production', 'main', 'abc123', true));
event(new DeploymentRollback('production', 'Health check failed'));
```

### Using Trait in Commands

```php
use App\Traits\DeploymentNotifiable;

class DeployCommand extends Command
{
    use DeploymentNotifiable;
    
    public function handle()
    {
        $this->notifyDeploymentStarted('production', 'main', 'abc123');
        
        // ... deployment logic ...
        
        if ($success) {
            $this->notifyDeploymentSuccess('production', 'main', 'abc123');
        } else {
            $this->notifyDeploymentFailure('production', 'main', 'abc123', $error);
        }
    }
}
```

## CLI Commands

### Send Notifications from CI/CD

```bash
# Notify deployment started
php artisan deployment:notify started production --branch=main --commit=abc123

# Notify deployment success with details
php artisan deployment:notify success production --branch=main --commit=abc123 --details="duration=2m30s" --details="migrations=3"

# Notify deployment failure
php artisan deployment:notify failure production --branch=main --commit=abc123 --error="Migration failed"

# Notify rollback
php artisan deployment:notify rollback production --reason="Health check failed" --previous-commit=def456
```

### Test Notifications

```bash
# Test different notification types
php artisan deployment:test-notification started --environment=staging
php artisan deployment:test-notification success --environment=production
php artisan deployment:test-notification failure --environment=staging
php artisan deployment:test-notification rollback --environment=production
```

## GitHub Actions Integration

Add these steps to your GitHub Actions workflow:

```yaml
- name: Notify Deployment Started
  run: php artisan deployment:notify started ${{ github.ref_name }} --branch=${{ github.ref_name }} --commit=${{ github.sha }}

- name: Deploy Application
  run: |
    # Your deployment steps here
    
- name: Notify Deployment Success
  if: success()
  run: php artisan deployment:notify success ${{ github.ref_name }} --branch=${{ github.ref_name }} --commit=${{ github.sha }} --details="workflow_run_id=${{ github.run_id }}"

- name: Notify Deployment Failure
  if: failure()
  run: php artisan deployment:notify failure ${{ github.ref_name }} --branch=${{ github.ref_name }} --commit=${{ github.sha }} --error="Deployment workflow failed"
```

## Notification Templates

### Slack Message Format
- Color-coded attachments based on notification type
- Structured fields showing environment, branch, commit, etc.
- Links to application URLs for successful deployments

### Email Format
- HTML template with responsive design
- Color-coded headers based on notification type
- Detailed tables showing deployment information
- Error details in highlighted boxes for failures

### Webhook Payload
```json
{
  "type": "deployment_success",
  "environment": "production",
  "branch": "main",
  "commit": "abc123",
  "timestamp": "2024-01-01T12:00:00Z",
  "message": "✅ Deployment successful for production environment",
  "url": "https://restant.main.susankshakya.com.np",
  "details": {
    "duration": "2m 30s",
    "migrations_run": 3
  }
}
```

## Configuration

All notification settings are configured in `config/deployment.php`:

```php
'notifications' => [
    'channels' => [
        'slack' => [
            'enabled' => env('DEPLOYMENT_SLACK_ENABLED', false),
            'webhook_url' => env('DEPLOYMENT_SLACK_WEBHOOK_URL'),
        ],
        'email' => [
            'enabled' => env('DEPLOYMENT_EMAIL_ENABLED', false),
            'recipients' => array_filter(explode(',', env('DEPLOYMENT_EMAIL_RECIPIENTS', ''))),
        ],
        'webhook' => [
            'enabled' => env('DEPLOYMENT_WEBHOOK_ENABLED', false),
            'url' => env('DEPLOYMENT_WEBHOOK_URL'),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => env('DEPLOYMENT_WEBHOOK_AUTH_HEADER'),
            ],
        ],
    ],
],
```

## Error Handling

The notification system includes robust error handling:
- Failed notifications are logged but don't stop the deployment process
- Each channel is tried independently
- Detailed error messages are logged for troubleshooting
- Graceful degradation when notification services are unavailable

## Requirements Satisfied

This implementation satisfies the following requirements:

- **9.1**: Notifications sent when deployment starts via configured channels
- **9.2**: Success notifications include deployment details and application URLs  
- **9.3**: Failure notifications include error details and context
- **9.4**: Rollback notifications include reason and previous commit information