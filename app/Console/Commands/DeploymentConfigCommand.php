<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DeploymentService;

class DeploymentConfigCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deployment:config 
                            {--show : Show current deployment configuration}
                            {--environment= : Show configuration for specific environment}
                            {--validate : Validate deployment configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage deployment configuration and environment detection';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('show')) {
            $this->showConfiguration();
        } elseif ($this->option('validate')) {
            $this->validateConfiguration();
        } else {
            $this->showCurrentEnvironment();
        }
    }

    /**
     * Show current environment information.
     */
    protected function showCurrentEnvironment()
    {
        $environment = DeploymentService::getCurrentEnvironment();
        $config = DeploymentService::getEnvironmentConfig();

        $this->info("Current Deployment Environment: {$environment}");
        $this->line('');

        $this->table(['Setting', 'Value'], [
            ['Environment', $environment],
            ['Subdomain', DeploymentService::getSubdomain() ?? 'N/A'],
            ['Domain', DeploymentService::getDomain() ?? 'N/A'],
            ['Dokku App', DeploymentService::getDokkuApp() ?? 'N/A'],
            ['SSL Enabled', DeploymentService::isSslEnabled() ? 'Yes' : 'No'],
            ['Debug Mode', $config['debug'] ?? false ? 'Yes' : 'No'],
            ['Log Level', $config['log_level'] ?? 'N/A'],
        ]);

        $this->line('');
        $this->info('Monitoring Services:');
        
        $monitoringServices = ['sentry', 'flagsmith', 'grafana'];
        $monitoringData = [];
        
        foreach ($monitoringServices as $service) {
            $enabled = DeploymentService::isMonitoringEnabled($service);
            $monitoringData[] = [ucfirst($service), $enabled ? 'Enabled' : 'Disabled'];
        }
        
        $this->table(['Service', 'Status'], $monitoringData);
    }

    /**
     * Show configuration for all or specific environment.
     */
    protected function showConfiguration()
    {
        $environment = $this->option('environment');
        
        if ($environment) {
            $this->showEnvironmentConfig($environment);
        } else {
            $this->showAllEnvironments();
        }
    }

    /**
     * Show configuration for a specific environment.
     */
    protected function showEnvironmentConfig(string $environment)
    {
        $config = config("deployment.environments.{$environment}");
        
        if (!$config) {
            $this->error("Environment '{$environment}' not found in configuration.");
            return;
        }

        $this->info("Configuration for environment: {$environment}");
        $this->line('');

        $configData = [];
        foreach ($config as $key => $value) {
            $configData[] = [$key, is_bool($value) ? ($value ? 'true' : 'false') : $value];
        }

        $this->table(['Setting', 'Value'], $configData);
    }

    /**
     * Show all environments.
     */
    protected function showAllEnvironments()
    {
        $environments = config('deployment.environments', []);
        
        $this->info('Available Deployment Environments:');
        $this->line('');

        foreach ($environments as $env => $config) {
            $this->line("<comment>{$env}:</comment>");
            
            $envData = [];
            foreach (['subdomain', 'branch', 'dokku_app', 'domain'] as $key) {
                if (isset($config[$key])) {
                    $envData[] = ["  {$key}", $config[$key]];
                }
            }
            
            $this->table(['Setting', 'Value'], $envData);
            $this->line('');
        }
    }

    /**
     * Validate deployment configuration.
     */
    protected function validateConfiguration()
    {
        $this->info('Validating deployment configuration...');
        $this->line('');

        $errors = [];
        $warnings = [];

        // Validate environments configuration
        $environments = config('deployment.environments', []);
        
        if (empty($environments)) {
            $errors[] = 'No deployment environments configured';
        }

        foreach ($environments as $env => $config) {
            // Check required fields for standard environments
            if (in_array($env, ['production', 'staging'])) {
                $required = ['subdomain', 'branch', 'dokku_app', 'domain'];
                
                foreach ($required as $field) {
                    if (!isset($config[$field])) {
                        $errors[] = "Environment '{$env}' missing required field: {$field}";
                    }
                }
            }
        }

        // Validate monitoring configuration
        $monitoring = config('deployment.monitoring', []);
        
        foreach (['sentry', 'flagsmith', 'grafana'] as $service) {
            if (!isset($monitoring[$service])) {
                $warnings[] = "Monitoring service '{$service}' not configured";
            }
        }

        // Display results
        if (empty($errors) && empty($warnings)) {
            $this->info('✓ Configuration validation passed');
        } else {
            if (!empty($errors)) {
                $this->error('Configuration Errors:');
                foreach ($errors as $error) {
                    $this->line("  • {$error}");
                }
                $this->line('');
            }

            if (!empty($warnings)) {
                $this->warn('Configuration Warnings:');
                foreach ($warnings as $warning) {
                    $this->line("  • {$warning}");
                }
            }
        }
    }
}