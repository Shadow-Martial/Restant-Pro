<?php

namespace App\Traits;

use App\Events\DeploymentStarted;
use App\Events\DeploymentCompleted;
use App\Events\DeploymentRollback;
use App\Facades\DeploymentNotification;

trait DeploymentNotifiable
{
    /**
     * Notify that deployment has started
     */
    protected function notifyDeploymentStarted(string $environment, string $branch, string $commit, array $metadata = []): void
    {
        event(new DeploymentStarted($environment, $branch, $commit, $metadata));
    }

    /**
     * Notify that deployment has completed successfully
     */
    protected function notifyDeploymentSuccess(string $environment, string $branch, string $commit, array $details = []): void
    {
        event(new DeploymentCompleted($environment, $branch, $commit, true, null, $details));
    }

    /**
     * Notify that deployment has failed
     */
    protected function notifyDeploymentFailure(string $environment, string $branch, string $commit, string $error, array $details = []): void
    {
        event(new DeploymentCompleted($environment, $branch, $commit, false, $error, $details));
    }

    /**
     * Notify that rollback has occurred
     */
    protected function notifyRollback(string $environment, string $reason, ?string $previousCommit = null, array $details = []): void
    {
        event(new DeploymentRollback($environment, $reason, $previousCommit, $details));
    }

    /**
     * Send notification directly (bypassing events)
     */
    protected function sendDirectNotification(): DeploymentNotification
    {
        return app(DeploymentNotification::class);
    }
}