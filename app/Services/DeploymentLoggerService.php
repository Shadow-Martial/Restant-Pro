<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Exception;

class DeploymentLoggerService
{
    private string $logChannel;
    private string $logPath;
    
    public function __construct()
    {
        $this->logChannel = 'deployment';
        $this->logPath = 'deployment-logs';
    }

    /**
     * Log deployment start
     */
    public function logDeploymentStart(string $appName, array $context = []): string
    {
        $deploymentId = $this->generateDeploymentId();
        
        $logData = [
            'deployment_id' => $deploymentId,
            'app_name' => $appName,
            'status' => 'started',
            'timestamp' => now()->toISOString(),
            'context' => $context
        ];
        
        $this->writeDeploymentLog($deploymentId, 'DEPLOYMENT_START', $logData);
        
        Log::channel($this->logChannel)->info('Deployment started', $logData);
        
        return $deploymentId;
    }

    /**
     * Log deployment step
     */
    public function logDeploymentStep(string $deploymentId, string $step, array $context = []): void
    {
        $logData = [
            'deployment_id' => $deploymentId,
            'step' => $step,
            'timestamp' => now()->toISOString(),
            'context' => $context
        ];
        
        $this->writeDeploymentLog($deploymentId, 'DEPLOYMENT_STEP', $logData);
        
        Log::channel($this->logChannel)->info("Deployment step: {$step}", $logData);
    }

    /**
     * Log deployment success
     */
    public function logDeploymentSuccess(string $deploymentId, array $context = []): void
    {
        $logData = [
            'deployment_id' => $deploymentId,
            'status' => 'success',
            'timestamp' => now()->toISOString(),
            'context' => $context
        ];
        
        $this->writeDeploymentLog($deploymentId, 'DEPLOYMENT_SUCCESS', $logData);
        
        Log::channel($this->logChannel)->info('Deployment completed successfully', $logData);
    }

    /**
     * Log deployment failure with detailed error capture
     */
    public function logDeploymentFailure(string $deploymentId, Exception $error, array $context = []): void
    {
        $errorData = [
            'deployment_id' => $deploymentId,
            'status' => 'failed',
            'timestamp' => now()->toISOString(),
            'error' => [
                'message' => $error->getMessage(),
                'code' => $error->getCode(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => $error->getTraceAsString()
            ],
            'context' => $context
        ];
        
        $this->writeDeploymentLog($deploymentId, 'DEPLOYMENT_FAILURE', $errorData);
        
        Log::channel($this->logChannel)->error('Deployment failed', $errorData);
        
        // Send to Sentry for error tracking
        if (app()->bound('sentry')) {
            app('sentry')->captureException($error, [
                'tags' => [
                    'deployment_id' => $deploymentId,
                    'type' => 'deployment_failure'
                ],
                'extra' => $context
            ]);
        }
    }

    /**
     * Log rollback event
     */
    public function logRollback(string $deploymentId, string $reason, array $context = []): void
    {
        $logData = [
            'deployment_id' => $deploymentId,
            'action' => 'rollback',
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
            'context' => $context
        ];
        
        $this->writeDeploymentLog($deploymentId, 'DEPLOYMENT_ROLLBACK', $logData);
        
        Log::channel($this->logChannel)->warning('Deployment rollback initiated', $logData);
    }

    /**
     * Capture command output for debugging
     */
    public function logCommandExecution(string $deploymentId, string $command, string $output, string $errorOutput = '', int $exitCode = 0): void
    {
        $logData = [
            'deployment_id' => $deploymentId,
            'command' => $command,
            'exit_code' => $exitCode,
            'output' => $output,
            'error_output' => $errorOutput,
            'timestamp' => now()->toISOString()
        ];
        
        $this->writeDeploymentLog($deploymentId, 'COMMAND_EXECUTION', $logData);
        
        if ($exitCode !== 0) {
            Log::channel($this->logChannel)->error('Command execution failed', $logData);
        } else {
            Log::channel($this->logChannel)->debug('Command executed successfully', $logData);
        }
    }

    /**
     * Get deployment logs for a specific deployment
     */
    public function getDeploymentLogs(string $deploymentId): array
    {
        try {
            $logFile = "{$this->logPath}/{$deploymentId}.log";
            
            if (!Storage::exists($logFile)) {
                return [];
            }
            
            $logContent = Storage::get($logFile);
            $logs = [];
            
            foreach (explode("\n", $logContent) as $line) {
                if (trim($line)) {
                    $logs[] = json_decode($line, true);
                }
            }
            
            return $logs;
            
        } catch (Exception $e) {
            Log::error('Failed to retrieve deployment logs', [
                'deployment_id' => $deploymentId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Get recent deployment logs
     */
    public function getRecentDeploymentLogs(int $limit = 10): array
    {
        try {
            $files = Storage::files($this->logPath);
            
            // Sort by modification time (most recent first)
            usort($files, function($a, $b) {
                return Storage::lastModified($b) - Storage::lastModified($a);
            });
            
            $recentLogs = [];
            $count = 0;
            
            foreach ($files as $file) {
                if ($count >= $limit) break;
                
                $deploymentId = basename($file, '.log');
                $logs = $this->getDeploymentLogs($deploymentId);
                
                if (!empty($logs)) {
                    $recentLogs[] = [
                        'deployment_id' => $deploymentId,
                        'logs' => $logs
                    ];
                    $count++;
                }
            }
            
            return $recentLogs;
            
        } catch (Exception $e) {
            Log::error('Failed to retrieve recent deployment logs', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Write deployment log to file
     */
    private function writeDeploymentLog(string $deploymentId, string $event, array $data): void
    {
        try {
            $logEntry = [
                'event' => $event,
                'data' => $data,
                'timestamp' => now()->toISOString()
            ];
            
            $logFile = "{$this->logPath}/{$deploymentId}.log";
            $logLine = json_encode($logEntry) . "\n";
            
            Storage::append($logFile, $logLine);
            
        } catch (Exception $e) {
            Log::error('Failed to write deployment log', [
                'deployment_id' => $deploymentId,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate unique deployment ID
     */
    private function generateDeploymentId(): string
    {
        return 'deploy_' . Carbon::now()->format('Y_m_d_H_i_s') . '_' . substr(md5(uniqid()), 0, 8);
    }

    /**
     * Clean up old deployment logs
     */
    public function cleanupOldLogs(int $daysToKeep = 30): void
    {
        try {
            $files = Storage::files($this->logPath);
            $cutoffTime = now()->subDays($daysToKeep)->timestamp;
            
            foreach ($files as $file) {
                if (Storage::lastModified($file) < $cutoffTime) {
                    Storage::delete($file);
                    Log::info('Deleted old deployment log', ['file' => $file]);
                }
            }
            
        } catch (Exception $e) {
            Log::error('Failed to cleanup old deployment logs', [
                'error' => $e->getMessage()
            ]);
        }
    }
}