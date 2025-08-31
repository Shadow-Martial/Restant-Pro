<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\DeploymentRollbackService;
use App\Services\DeploymentLoggerService;
use App\Services\DeploymentNotificationService;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class DeploymentFailureHandler
{
    private DeploymentRollbackService $rollbackService;
    private DeploymentLoggerService $loggerService;
    private DeploymentNotificationService $notificationService;

    public function __construct(
        DeploymentRollbackService $rollbackService,
        DeploymentLoggerService $loggerService,
        DeploymentNotificationService $notificationService
    ) {
        $this->rollbackService = $rollbackService;
        $this->loggerService = $loggerService;
        $this->notificationService = $notificationService;
    }

    /**
     * Handle an incoming request and catch deployment-related failures
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (Exception $exception) {
            // Check if this is a deployment-related request
            if ($this->isDeploymentRequest($request)) {
                $this->handleDeploymentFailure($request, $exception);
            }
            
            // Re-throw the exception to maintain normal error handling
            throw $exception;
        }
    }

    /**
     * Check if the request is deployment-related
     */
    private function isDeploymentRequest(Request $request): bool
    {
        // Check for deployment-related routes or headers
        $deploymentRoutes = [
            'deployment',
            'deploy',
            'webhook/github',
            'webhook/dokku'
        ];

        $path = $request->path();
        
        foreach ($deploymentRoutes as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        // Check for deployment headers (e.g., from GitHub webhooks)
        return $request->hasHeader('X-GitHub-Event') || 
               $request->hasHeader('X-Dokku-Event');
    }

    /**
     * Handle deployment failure
     */
    private function handleDeploymentFailure(Request $request, Exception $exception): void
    {
        try {
            // Extract deployment information from request
            $deploymentInfo = $this->extractDeploymentInfo($request);
            
            if (!$deploymentInfo) {
                Log::warning('Could not extract deployment info from failed request', [
                    'path' => $request->path(),
                    'method' => $request->method()
                ]);
                return;
            }

            $appName = $deploymentInfo['app_name'];
            $deploymentId = $deploymentInfo['deployment_id'] ?? 'unknown';

            // Log the deployment failure
            $this->loggerService->logDeploymentFailure($deploymentId, $exception, [
                'request_path' => $request->path(),
                'request_method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip()
            ]);

            // Send failure notification
            $this->notificationService->sendFailureNotification(
                $appName,
                $deploymentId,
                $exception,
                $deploymentInfo
            );

            // Attempt automatic rollback if enabled
            if (config('deployment.rollback.automatic', true)) {
                $this->attemptAutomaticRollback($appName, $exception->getMessage());
            }

        } catch (Exception $e) {
            Log::error('Failed to handle deployment failure', [
                'original_error' => $exception->getMessage(),
                'handler_error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Extract deployment information from request
     */
    private function extractDeploymentInfo(Request $request): ?array
    {
        // Try to extract from GitHub webhook
        if ($request->hasHeader('X-GitHub-Event')) {
            return $this->extractFromGitHubWebhook($request);
        }

        // Try to extract from Dokku webhook
        if ($request->hasHeader('X-Dokku-Event')) {
            return $this->extractFromDokkuWebhook($request);
        }

        // Try to extract from request parameters
        if ($request->has(['app_name', 'deployment_id'])) {
            return [
                'app_name' => $request->input('app_name'),
                'deployment_id' => $request->input('deployment_id'),
                'source' => 'request_params'
            ];
        }

        // Try to extract from route parameters
        $routeParams = $request->route()?->parameters() ?? [];
        if (isset($routeParams['app']) || isset($routeParams['appName'])) {
            return [
                'app_name' => $routeParams['app'] ?? $routeParams['appName'],
                'deployment_id' => $routeParams['deployment'] ?? 'route_' . time(),
                'source' => 'route_params'
            ];
        }

        return null;
    }

    /**
     * Extract deployment info from GitHub webhook
     */
    private function extractFromGitHubWebhook(Request $request): ?array
    {
        $payload = $request->json();
        
        if (!$payload || !isset($payload['ref'])) {
            return null;
        }

        $branch = str_replace('refs/heads/', '', $payload['ref']);
        $appName = $this->getAppNameFromBranch($branch);

        return [
            'app_name' => $appName,
            'deployment_id' => 'github_' . ($payload['after'] ?? time()),
            'branch' => $branch,
            'commit' => $payload['after'] ?? null,
            'source' => 'github_webhook'
        ];
    }

    /**
     * Extract deployment info from Dokku webhook
     */
    private function extractFromDokkuWebhook(Request $request): ?array
    {
        $payload = $request->json();
        
        if (!$payload || !isset($payload['app'])) {
            return null;
        }

        return [
            'app_name' => $payload['app'],
            'deployment_id' => 'dokku_' . time(),
            'source' => 'dokku_webhook'
        ];
    }

    /**
     * Get app name from branch name
     */
    private function getAppNameFromBranch(string $branch): string
    {
        $environments = config('deployment.environments', []);
        
        foreach ($environments as $env => $config) {
            if ($config['branch'] === $branch) {
                return $config['dokku_app'];
            }
        }

        // Default mapping
        return match($branch) {
            'main' => 'restant-main',
            'staging' => 'restant-staging',
            default => "restant-{$branch}"
        };
    }

    /**
     * Attempt automatic rollback
     */
    private function attemptAutomaticRollback(string $appName, string $reason): void
    {
        try {
            Log::info("Attempting automatic rollback for {$appName}", [
                'reason' => $reason,
                'timestamp' => now()
            ]);

            $rollbackResult = $this->rollbackService->performAutomaticRollback($appName, $reason);

            if ($rollbackResult) {
                Log::info("Automatic rollback successful for {$appName}");
            } else {
                Log::error("Automatic rollback failed for {$appName}");
            }

        } catch (Exception $e) {
            Log::error("Exception during automatic rollback for {$appName}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}