<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DeploymentNotificationService;

class DeploymentNotifyCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'deployment:notify 
                            {type : The notification type (started|success|failure|rollback)}
                            {environment : The deployment environment}
                            {--branch= : The git branch}
                            {--commit= : The git commit hash}
                            {--error= : Error message for failure notifications}
                            {--reason= : Reason for rollback notifications}
                            {--previous-commit= : Previous commit for rollback}
                            {--details=* : Additional details as key=value pairs}';

    /**
     * The console command description.
     */
    protected $description = 'Send deployment notifications from CI/CD pipeline';

    /**
     * Execute the console command.
     */
    public function handle(DeploymentNotificationService $notificationService): int
    {
        $type = $this->argument('type');
        $environment = $this->argument('environment');
        $branch = $this->option('branch') ?? 'unknown';
        $commit = $this->option('commit') ?? 'unknown';

        // Parse additional details
        $details = [];
        foreach ($this->option('details') as $detail) {
            if (str_contains($detail, '=')) {
                [$key, $value] = explode('=', $detail, 2);
                $details[$key] = $value;
            }
        }

        try {
            match ($type) {
                'started' => $notificationService->notifyDeploymentStarted(
                    $environment,
                    $branch,
                    $commit
                ),
                'success' => $notificationService->notifyDeploymentSuccess(
                    $environment,
                    $branch,
                    $commit,
                    $details
                ),
                'failure' => $notificationService->notifyDeploymentFailure(
                    $environment,
                    $branch,
                    $commit,
                    $this->option('error') ?? 'Deployment failed',
                    $details
                ),
                'rollback' => $notificationService->notifyRollback(
                    $environment,
                    $this->option('reason') ?? 'Rollback triggered',
                    $this->option('previous-commit'),
                    $details
                ),
                default => throw new \InvalidArgumentException("Invalid notification type: {$type}")
            };

            $this->info("Deployment notification sent successfully");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to send notification: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}