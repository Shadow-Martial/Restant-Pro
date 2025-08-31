<?php

namespace App\Logging;

use Monolog\Logger;
use App\Services\GrafanaCloudService;

class CreateGrafanaCloudLogger
{
    /**
     * Create a custom Monolog instance for Grafana Cloud logging.
     */
    public function __invoke(array $config): Logger
    {
        $grafanaService = app(GrafanaCloudService::class);
        
        $logger = new Logger('grafana-cloud');
        
        $handler = new GrafanaCloudLogHandler(
            $grafanaService,
            $config['level'] ?? Logger::DEBUG
        );
        
        $logger->pushHandler($handler);
        
        return $logger;
    }
}