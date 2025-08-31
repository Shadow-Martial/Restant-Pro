<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class DeploymentNotificationService
{
    protected array $channels;
    protected array $config;

    public function __construct()
    {
        $this->config = config('deployment.notifications', []);
        $this->channels = $this->config['channels'] ?? [];
    }

    /**
     * Send notification when deployment starts
     */
    public function notifyDeploymentStarted(string $environment, string $branch, string $commit): void
    {
        $data = [
            'type' => 'deployment_started',
            'environment' => $environment,
            'branch' => $branch,
            'commit' => $commit,
            'timestamp' => now()->toISOString(),
            'message' => "🚀 Deployment started for {$environment} environment from branch {$branch}"
        ];

        $this->sendNotification($data);
        Log::info('Deployment started notification sent', $data);
    }

    /**
     * Send notification when deployment succeeds
     */
    public function notifyDeploymentSuccess(string $environment, string $branch, string $commit, array $details = []): void
    {
        $data = [
            'type' => 'deployment_success',
            'environment' => $environment,
            'branch' => $branch,
            'commit' => $commit,
            'timestamp' => now()->toISOString(),
            'details' => $details,
            'message' => "✅ Deployment successful for {$environment} environment",
            'url' => $this->getEnvironmentUrl($environment)
        ];

        $this->sendNotification($data);
        Log::info('Deployment success notification sent', $data);
    }

    /**
     * Send notification when deployment fails
     */
    public function notifyDeploymentFailure(string $environment, string $branch, string $commit, string $error, array $details = []): void
    {
        $data = [
            'type' => 'deployment_failure',
            'environment' => $environment,
            'branch' => $branch,
            'commit' => $commit,
            'timestamp' => now()->toISOString(),
            'error' => $error,
            'details' => $details,
            'message' => "❌ Deployment failed for {$environment} environment"
        ];

        $this->sendNotification($data);
        Log::error('Deployment failure notification sent', $data);
    }

    /**
     * Send notification when rollback occurs
     */
    public function notifyRollback(string $environment, string $reason, string $previousCommit = null, array $details = []): void
    {
        $data = [
            'type' => 'deployment_rollback',
            'environment' => $environment,
            'reason' => $reason,
            'previous_commit' => $previousCommit,
            'timestamp' => now()->toISOString(),
            'details' => $details,
            'message' => "🔄 Rollback executed for {$environment} environment. Reason: {$reason}"
        ];

        $this->sendNotification($data);
        Log::warning('Deployment rollback notification sent', $data);
    }

    /**
     * Send notification to all configured channels
     */
    protected function sendNotification(array $data): void
    {
        foreach ($this->channels as $channel => $config) {
            if (!($config['enabled'] ?? false)) {
                continue;
            }

            try {
                match ($channel) {
                    'slack' => $this->sendSlackNotification($data, $config),
                    'email' => $this->sendEmailNotification($data, $config),
                    'webhook' => $this->sendWebhookNotification($data, $config),
                    default => Log::warning("Unknown notification channel: {$channel}")
                };
            } catch (\Exception $e) {
                Log::error("Failed to send notification via {$channel}", [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
            }
        }
    }

    /**
     * Send Slack notification
     */
    protected function sendSlackNotification(array $data, array $config): void
    {
        $webhookUrl = $config['webhook_url'] ?? null;
        if (!$webhookUrl) {
            throw new \Exception('Slack webhook URL not configured');
        }

        $color = match ($data['type']) {
            'deployment_started' => '#36a64f',
            'deployment_success' => '#36a64f',
            'deployment_failure' => '#ff0000',
            'deployment_rollback' => '#ff9900',
            default => '#36a64f'
        };

        $payload = [
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $data['message'],
                    'fields' => $this->buildSlackFields($data),
                    'footer' => 'Deployment System',
                    'ts' => now()->timestamp
                ]
            ]
        ];

        Http::post($webhookUrl, $payload);
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification(array $data, array $config): void
    {
        $recipients = $config['recipients'] ?? [];
        if (empty($recipients)) {
            throw new \Exception('Email recipients not configured');
        }

        Mail::send(
            'emails.deployment-notification',
            ['data' => $data],
            function ($message) use ($data, $recipients) {
                $message->to($recipients)
                        ->subject($this->getEmailSubject($data))
                        ->text('emails.deployment-notification-text', ['data' => $data]);
            }
        );
    }

    /**
     * Send webhook notification
     */
    protected function sendWebhookNotification(array $data, array $config): void
    {
        $url = $config['url'] ?? null;
        if (!$url) {
            throw new \Exception('Webhook URL not configured');
        }

        $headers = $config['headers'] ?? [];
        
        Http::withHeaders($headers)->post($url, $data);
    }

    /**
     * Build Slack message fields
     */
    protected function buildSlackFields(array $data): array
    {
        $fields = [
            [
                'title' => 'Environment',
                'value' => $data['environment'],
                'short' => true
            ]
        ];

        if (isset($data['branch'])) {
            $fields[] = [
                'title' => 'Branch',
                'value' => $data['branch'],
                'short' => true
            ];
        }

        if (isset($data['commit'])) {
            $fields[] = [
                'title' => 'Commit',
                'value' => substr($data['commit'], 0, 8),
                'short' => true
            ];
        }

        if (isset($data['url'])) {
            $fields[] = [
                'title' => 'URL',
                'value' => $data['url'],
                'short' => false
            ];
        }

        if (isset($data['error'])) {
            $fields[] = [
                'title' => 'Error',
                'value' => $data['error'],
                'short' => false
            ];
        }

        if (isset($data['reason'])) {
            $fields[] = [
                'title' => 'Reason',
                'value' => $data['reason'],
                'short' => false
            ];
        }

        return $fields;
    }

    /**
     * Get email subject based on notification type
     */
    protected function getEmailSubject(array $data): string
    {
        return match ($data['type']) {
            'deployment_started' => "[Deployment] Started - {$data['environment']}",
            'deployment_success' => "[Deployment] Success - {$data['environment']}",
            'deployment_failure' => "[Deployment] Failed - {$data['environment']}",
            'deployment_rollback' => "[Deployment] Rollback - {$data['environment']}",
            default => "[Deployment] Notification - {$data['environment']}"
        };
    }

    /**
     * Get environment URL
     */
    protected function getEnvironmentUrl(string $environment): string
    {
        $subdomain = config("deployment.environments.{$environment}.subdomain", $environment);
        return "https://restant.{$subdomain}.susankshakya.com.np";
    }
}