<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Exception;

class DeploymentRollbackService
{
    private string $dokkuHost;
    private string $sshKey;
    
    public function __construct()
    {
        $this->dokkuHost = config('deployment.dokku.host');
        $this->sshKey = config('deployment.dokku.ssh_key_path');
    }

    /**
     * Automatically rollback deployment on failure
     */
    public function performAutomaticRollback(string $appName, string $reason = ''): bool
    {
        try {
            Log::info("Starting automatic rollback for app: {$appName}", [
                'app' => $appName,
                'reason' => $reason,
                'timestamp' => now()
            ]);

            // Get previous release
            $previousRelease = $this->getPreviousRelease($appName);
            
            if (!$previousRelease) {
                Log::error("No previous release found for rollback", ['app' => $appName]);
                return false;
            }

            // Perform rollback
            $rollbackSuccess = $this->rollbackToRelease($appName, $previousRelease);
            
            if ($rollbackSuccess) {
                Log::info("Automatic rollback completed successfully", [
                    'app' => $appName,
                    'previous_release' => $previousRelease,
                    'reason' => $reason
                ]);
                
                // Send rollback notification
                app(DeploymentNotificationService::class)->sendRollbackNotification(
                    $appName, 
                    $previousRelease, 
                    $reason
                );
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Log::error("Automatic rollback failed", [
                'app' => $appName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    /**
     * Get the previous release for rollback
     */
    private function getPreviousRelease(string $appName): ?string
    {
        try {
            $command = "ssh -i {$this->sshKey} dokku@{$this->dokkuHost} ps:report {$appName} --deployed";
            $result = Process::run($command);
            
            if ($result->successful()) {
                $releases = explode("\n", trim($result->output()));
                // Return the second most recent release (previous one)
                return count($releases) > 1 ? $releases[1] : null;
            }
            
            return null;
            
        } catch (Exception $e) {
            Log::error("Failed to get previous release", [
                'app' => $appName,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }

    /**
     * Rollback to a specific release
     */
    private function rollbackToRelease(string $appName, string $release): bool
    {
        try {
            // Stop current processes
            $stopCommand = "ssh -i {$this->sshKey} dokku@{$this->dokkuHost} ps:stop {$appName}";
            Process::run($stopCommand);
            
            // Rollback to previous release
            $rollbackCommand = "ssh -i {$this->sshKey} dokku@{$this->dokkuHost} ps:rebuild {$appName}";
            $result = Process::run($rollbackCommand);
            
            if (!$result->successful()) {
                Log::error("Rollback command failed", [
                    'app' => $appName,
                    'command' => $rollbackCommand,
                    'output' => $result->output(),
                    'error' => $result->errorOutput()
                ]);
                return false;
            }
            
            // Verify rollback success
            return $this->verifyRollbackSuccess($appName);
            
        } catch (Exception $e) {
            Log::error("Rollback execution failed", [
                'app' => $appName,
                'release' => $release,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Verify that rollback was successful
     */
    private function verifyRollbackSuccess(string $appName): bool
    {
        try {
            // Wait a moment for services to start
            sleep(10);
            
            // Check if app is running
            $statusCommand = "ssh -i {$this->sshKey} dokku@{$this->dokkuHost} ps:report {$appName} --deployed";
            $result = Process::run($statusCommand);
            
            if ($result->successful() && str_contains($result->output(), 'true')) {
                // Perform basic health check
                return app(HealthCheckService::class)->performBasicHealthCheck($appName);
            }
            
            return false;
            
        } catch (Exception $e) {
            Log::error("Rollback verification failed", [
                'app' => $appName,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Manual rollback with specific release
     */
    public function performManualRollback(string $appName, string $targetRelease = null): array
    {
        try {
            Log::info("Starting manual rollback", [
                'app' => $appName,
                'target_release' => $targetRelease,
                'initiated_by' => auth()->user()->email ?? 'system'
            ]);

            if (!$targetRelease) {
                $targetRelease = $this->getPreviousRelease($appName);
            }
            
            if (!$targetRelease) {
                return [
                    'success' => false,
                    'message' => 'No target release found for rollback'
                ];
            }

            $rollbackSuccess = $this->rollbackToRelease($appName, $targetRelease);
            
            if ($rollbackSuccess) {
                app(DeploymentNotificationService::class)->sendRollbackNotification(
                    $appName, 
                    $targetRelease, 
                    'Manual rollback initiated'
                );
                
                return [
                    'success' => true,
                    'message' => "Successfully rolled back to release: {$targetRelease}",
                    'release' => $targetRelease
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Rollback execution failed'
            ];
            
        } catch (Exception $e) {
            Log::error("Manual rollback failed", [
                'app' => $appName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage()
            ];
        }
    }
}