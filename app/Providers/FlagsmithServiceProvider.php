<?php

namespace App\Providers;

use App\Services\FlagsmithService;
use Illuminate\Support\ServiceProvider;

class FlagsmithServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FlagsmithService::class, function ($app) {
            return new FlagsmithService();
        });

        $this->app->alias(FlagsmithService::class, 'flagsmith');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/flagsmith.php' => config_path('flagsmith.php'),
        ], 'flagsmith-config');
    }
}