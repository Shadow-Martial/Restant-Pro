<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DeploymentNotificationService;

class TestDeploymentNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'deployment:test-notification 
                            {type : The notification type (started|success|failure|rollback)}
                            {--environment=staging : The deployment environment}
                            {--branch=main : The git branch}
                            {--commit=abc123 : The git commit hash}';

    /**
     * The console command description.
     */
    protected $description = 'Test deployment notification system';

    /**
     * Execute the console command.
     */
    public function handle(DeploymentNotificationService $notificationService): int
    {
        $type = $this->argument('type');
        $environment = $this->option('environment');
        $branch = $this->option('branch');
        $commit = $this->option('commit');

        $this->info("Testing {$type} notification for {$environment} environment...");

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
                    [
                        'duration' => '2m 30s',
                        'migrations_run' => 3,
                        'assets_compiled' => true
                    ]
                ),
                'failure' => $notificationService->notifyDeploymentFailure(
                    $environment,
                    $branch,
                    $commit,
                    'Migration failed: Table users already exists',
                    [
                        'step' => 'database_migration',
                        'exit_code' => 1
                    ]
                ),
                'rollback' => $notificationService->notifyRollback(
                    $environment,
                    'Health check failed after deployment',
                    'def456',
                    [
                        'health_check_url' => '/health',
                        'response_code' => 500
                    ]
                ),
                default => throw new \InvalidArgumentException("Invalid notification type: {$type}")
            };

            $this->info("✅ {$type} notification sent successfully!");
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Failed to send notification: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}