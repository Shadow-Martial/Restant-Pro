<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;
use App\Services\DeploymentService;

/**
 * @method static string getCurrentEnvironment()
 * @method static array getEnvironmentConfig(string $environment = null)
 * @method static bool isProduction()
 * @method static bool isStaging()
 * @method static bool isFeatureBranch()
 * @method static string|null getSubdomain()
 * @method static string|null getDomain()
 * @method static string|null getDokkuApp()
 * @method static bool isSslEnabled()
 * @method static array getMonitoringConfig(string $service)
 * @method static bool isMonitoringEnabled(string $service)
 * @method static string generateFeatureDomain(string $branch)
 * @method static string generateFeatureDokkuApp(string $branch)
 */
class Deployment extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return DeploymentService::class;
    }
}