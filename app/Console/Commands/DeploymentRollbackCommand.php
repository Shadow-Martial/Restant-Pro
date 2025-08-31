<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DeploymentRollbackService;
use App\Services\DeploymentLoggerService;
use Exception;

class DeploymentRollbackCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'deployment:rollback 
                            {app : The name of the app to rollback}
                            {--release= : Specific release to rollback to}
                            {--list : List available releases}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     */
    protected $description = 'Manually rollback a deployment to a previous release';

    private DeploymentRollbackService $rollbackService;
    private DeploymentLoggerService $loggerService;

    public function __construct(
        DeploymentRollbackService $rollbackService,
        DeploymentLoggerService $loggerService
    ) {
        parent::__construct();
        $this->rollbackService = $rollbackService;
        $this->loggerService = $loggerService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $appName = $this->argument('app');
        $targetRelease = $this->option('release');
        $listReleases = $this->option('list');
        $force = $this->option('force');

        try {
            // If list option is provided, show available releases
            if ($listReleases) {
                return $this->listAvailableReleases($appName);
            }

            // Validate app name
            if (!$this->validateAppName($appName)) {
                $this->error("Invalid app name: {$appName}");
                return 1;
            }

            // Show current status
            $this->info("Preparing rollback for app: {$appName}");
            
            if ($targetRelease) {
                $this->info("Target release: {$targetRelease}");
            } else {
                $this->info("Target release: Previous release (auto-detected)");
            }

            // Confirmation prompt (unless forced)
            if (!$force && !$this->confirm('Are you sure you want to proceed with the rollback?')) {
                $this->info('Rollback cancelled by user.');
                return 0;
            }

            // Start rollback process
            $deploymentId = $this->loggerService->logDeploymentStart($appName, [
                'type' => 'manual_rollback',
                'target_release' => $targetRelease,
                'initiated_by' => 'artisan_command'
            ]);

            $this->info("Starting rollback process (ID: {$deploymentId})...");

            // Perform rollback
            $result = $this->rollbackService->performManualRollback($appName, $targetRelease);

            if ($result['success']) {
                $this->loggerService->logDeploymentSuccess($deploymentId, [
                    'rollback_target' => $result['release']
                ]);
                
                $this->info("✅ Rollback completed successfully!");
                $this->info("Rolled back to release: {$result['release']}");
                
                return 0;
            } else {
                $this->loggerService->logDeploymentFailure(
                    $deploymentId, 
                    new Exception($result['message']),
                    ['rollback_attempt' => true]
                );
                
                $this->error("❌ Rollback failed: {$result['message']}");
                return 1;
            }

        } catch (Exception $e) {
            $this->error("Rollback command failed: {$e->getMessage()}");
            
            if (isset($deploymentId)) {
                $this->loggerService->logDeploymentFailure($deploymentId, $e, [
                    'command' => 'artisan_rollback'
                ]);
            }
            
            return 1;
        }
    }

    /**
     * List available releases for the app
     */
    private function listAvailableReleases(string $appName): int
    {
        try {
            $this->info("Available releases for {$appName}:");
            
            // This would need to be implemented in the rollback service
            // For now, show a placeholder
            $this->table(
                ['#', 'Release', 'Date', 'Status'],
                [
                    ['1', 'current', now()->format('Y-m-d H:i:s'), 'active'],
                    ['2', 'previous-1', now()->subHour()->format('Y-m-d H:i:s'), 'available'],
                    ['3', 'previous-2', now()->subHours(2)->format('Y-m-d H:i:s'), 'available'],
                ]
            );
            
            $this->info("Use --release=<release_name> to rollback to a specific release");
            
            return 0;
            
        } catch (Exception $e) {
            $this->error("Failed to list releases: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Validate app name format
     */
    private function validateAppName(string $appName): bool
    {
        // Basic validation - app name should follow restant-{environment} pattern
        return preg_match('/^restant-[a-zA-Z0-9\-]+$/', $appName);
    }
}