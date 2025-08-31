<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\DeploymentNotificationService;

class DeploymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(DeploymentNotificationService::class, function ($app) {
            return new DeploymentNotificationService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/deployment.php' => config_path('deployment.php'),
        ], 'deployment-config');
    }
}