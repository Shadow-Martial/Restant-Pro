<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;
use App\Services\DeploymentNotificationService;

/**
 * @method static void notifyDeploymentStarted(string $environment, string $branch, string $commit)
 * @method static void notifyDeploymentSuccess(string $environment, string $branch, string $commit, array $details = [])
 * @method static void notifyDeploymentFailure(string $environment, string $branch, string $commit, string $error, array $details = [])
 * @method static void notifyRollback(string $environment, string $reason, string $previousCommit = null, array $details = [])
 */
class DeploymentNotification extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return DeploymentNotificationService::class;
    }
}