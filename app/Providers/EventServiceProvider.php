<?php

namespace App\Providers;

use App\Events\DeploymentStarted;
use App\Events\DeploymentCompleted;
use App\Events\DeploymentRollback;
use App\Listeners\SendDeploymentNotifications;
use App\Listeners\SetRestaurantIdInSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Login::class => [
            SetRestaurantIdInSession::class,
        ],
        DeploymentStarted::class => [
            SendDeploymentNotifications::class . '@handleDeploymentStarted',
        ],
        DeploymentCompleted::class => [
            SendDeploymentNotifications::class . '@handleDeploymentCompleted',
        ],
        DeploymentRollback::class => [
            SendDeploymentNotifications::class . '@handleDeploymentRollback',
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {

    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
