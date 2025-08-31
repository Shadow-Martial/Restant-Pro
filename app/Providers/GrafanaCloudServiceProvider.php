<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\QueryExecuted;
use App\Services\GrafanaCloudService;

class GrafanaCloudServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(GrafanaCloudService::class, function ($app) {
            return new GrafanaCloudService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if (!config('monitoring.grafana.enabled', false)) {
            return;
        }

        $this->setupDatabaseQueryTracking();
        $this->setupInfrastructureMonitoring();
        $this->setupPerformanceTracking();
    }

    /**
     * Set up database query tracking
     */
    private function setupDatabaseQueryTracking(): void
    {
        if (!config('monitoring.grafana.apm.track_database_queries', true)) {
            return;
        }

        Event::listen(QueryExecuted::class, function (QueryExecuted $query) {
            $grafanaService = app(GrafanaCloudService::class);
            $grafanaService->trackDatabaseQuery(
                $query->connectionName,
                $query->time / 1000, // Convert milliseconds to seconds
                $query->sql
            );
        });
    }

    /**
     * Set up infrastructure monitoring
     */
    private function setupInfrastructureMonitoring(): void
    {
        if (!config('monitoring.grafana.infrastructure.enabled', true)) {
            return;
        }

        // Schedule infrastructure metrics collection
        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                return;
            }

            $interval = config('monitoring.grafana.infrastructure.collect_interval', 60);
            
            // Use a simple approach to collect metrics periodically
            // In a production environment, this should be handled by a scheduled job
            register_shutdown_function(function () {
                $grafanaService = app(GrafanaCloudService::class);
                $grafanaService->trackInfrastructureMetrics();
            });
        });
    }

    /**
     * Set up performance tracking
     */
    private function setupPerformanceTracking(): void
    {
        if (!config('monitoring.performance.enabled', true)) {
            return;
        }

        // Additional performance tracking can be added here
        // For now, most performance tracking is handled by the middleware
    }
}