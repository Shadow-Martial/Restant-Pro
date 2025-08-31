<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BladeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // @feature directive
        Blade::if('feature', function (string $flagName, ?string $identity = null) {
            return feature_enabled($flagName, $identity);
        });

        // @userfeature directive
        Blade::if('userfeature', function (string $flagName, $user = null) {
            return user_feature_enabled($flagName, $user);
        });

        // @tenantfeature directive
        Blade::if('tenantfeature', function (string $flagName) {
            return tenant_feature_enabled($flagName);
        });
    }
}