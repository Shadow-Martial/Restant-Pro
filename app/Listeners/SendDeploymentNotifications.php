<?php

namespace App\Listeners;

use App\Events\DeploymentStarted;
use App\Events\DeploymentCompleted;
use App\Events\DeploymentRollback;
use App\Services\DeploymentNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendDeploymentNotifications implements ShouldQueue
{
    use InteractsWithQueue;

    protected DeploymentNotificationService $notificationService;

    /**
     * Create the event listener.
     */
    public function __construct(DeploymentNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle deployment started events.
     */
    public function handleDeploymentStarted(DeploymentStarted $event): void
    {
        $this->notificationService->notifyDeploymentStarted(
            $event->environment,
            $event->branch,
            $event->commit
        );
    }

    /**
     * Handle deployment completed events.
     */
    public function handleDeploymentCompleted(DeploymentCompleted $event): void
    {
        if ($event->success) {
            $this->notificationService->notifyDeploymentSuccess(
                $event->environment,
                $event->branch,
                $event->commit,
                $event->details
            );
        } else {
            $this->notificationService->notifyDeploymentFailure(
                $event->environment,
                $event->branch,
                $event->commit,
                $event->error ?? 'Unknown error',
                $event->details
            );
        }
    }

    /**
     * Handle deployment rollback events.
     */
    public function handleDeploymentRollback(DeploymentRollback $event): void
    {
        $this->notificationService->notifyRollback(
            $event->environment,
            $event->reason,
            $event->previousCommit,
            $event->details
        );
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe($events): array
    {
        return [
            DeploymentStarted::class => 'handleDeploymentStarted',
            DeploymentCompleted::class => 'handleDeploymentCompleted',
            DeploymentRollback::class => 'handleDeploymentRollback',
        ];
    }
}