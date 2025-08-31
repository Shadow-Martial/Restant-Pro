<?php

namespace App\Console\Commands;

use App\Services\SecretManager;
use Illuminate\Console\Command;

class SecretManagementCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'secrets:manage 
                            {action : The action to perform (store|get|delete|list|rotate|sync)}
                            {key? : The secret key}
                            {value? : The secret value (for store/rotate actions)}
                            {--environment= : The environment (defaults to current)}';

    /**
     * The console command description.
     */
    protected $description = 'Manage application secrets securely';

    /**
     * Secret manager instance
     */
    protected SecretManager $secretManager;

    /**
     * Create a new command instance.
     */
    public function __construct(SecretManager $secretManager)
    {
        parent::__construct();
        $this->secretManager = $secretManager;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');
        $key = $this->argument('key');
        $value = $this->argument('value');
        $environment = $this->option('environment') ?? config('app.env');

        switch ($action) {
            case 'store':
                return $this->storeSecret($key, $value, $environment);

            case 'get':
                return $this->getSecret($key, $environment);

            case 'delete':
                return $this->deleteSecret($key, $environment);

            case 'list':
                return $this->listSecrets($environment);

            case 'rotate':
                return $this->rotateSecret($key, $value, $environment);

            case 'sync':
                return $this->syncSecrets();

            default:
                $this->error("Invalid action: {$action}");
                $this->info('Available actions: store, get, delete, list, rotate, sync');
                return 1;
        }
    }

    /**
     * Store a secret
     */
    protected function storeSecret(?string $key, ?string $value, string $environment): int
    {
        if (!$key) {
            $key = $this->ask('Enter secret key');
        }

        if (!$value) {
            $value = $this->secret('Enter secret value');
        }

        // Validate secret
        $errors = $this->secretManager->validateSecret($key, $value);
        if (!empty($errors)) {
            $this->error('Secret validation failed:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
            return 1;
        }

        if ($this->secretManager->store($key, $value, $environment)) {
            $this->info("Secret '{$key}' stored successfully for environment '{$environment}'");
            return 0;
        } else {
            $this->error("Failed to store secret '{$key}'");
            return 1;
        }
    }

    /**
     * Get a secret
     */
    protected function getSecret(?string $key, string $environment): int
    {
        if (!$key) {
            $key = $this->ask('Enter secret key');
        }

        $value = $this->secretManager->get($key, $environment);

        if ($value !== null) {
            $this->info("Secret '{$key}' for environment '{$environment}':");
            $this->line($value);
            return 0;
        } else {
            $this->error("Secret '{$key}' not found for environment '{$environment}'");
            return 1;
        }
    }

    /**
     * Delete a secret
     */
    protected function deleteSecret(?string $key, string $environment): int
    {
        if (!$key) {
            $key = $this->ask('Enter secret key');
        }

        if (!$this->confirm("Are you sure you want to delete secret '{$key}' for environment '{$environment}'?")) {
            $this->info('Operation cancelled');
            return 0;
        }

        if ($this->secretManager->delete($key, $environment)) {
            $this->info("Secret '{$key}' deleted successfully for environment '{$environment}'");
            return 0;
        } else {
            $this->error("Failed to delete secret '{$key}'");
            return 1;
        }
    }

    /**
     * List secrets
     */
    protected function listSecrets(string $environment): int
    {
        $secrets = $this->secretManager->list($environment);

        if (empty($secrets)) {
            $this->info("No secrets found for environment '{$environment}'");
            return 0;
        }

        $this->info("Secrets for environment '{$environment}':");
        $this->table(
            ['Key', 'Environment', 'Created At', 'Updated At'],
            collect($secrets)->map(function ($data, $key) {
                return [
                    $key,
                    $data['environment'],
                    $data['created_at'] ?? 'N/A',
                    $data['updated_at'] ?? 'N/A',
                ];
            })->toArray()
        );

        return 0;
    }

    /**
     * Rotate a secret
     */
    protected function rotateSecret(?string $key, ?string $value, string $environment): int
    {
        if (!$key) {
            $key = $this->ask('Enter secret key');
        }

        if (!$value) {
            if ($this->confirm('Generate a new random value?')) {
                $value = $this->secretManager->generateSecret();
                $this->info("Generated new value: {$value}");
            } else {
                $value = $this->secret('Enter new secret value');
            }
        }

        // Validate secret
        $errors = $this->secretManager->validateSecret($key, $value);
        if (!empty($errors)) {
            $this->error('Secret validation failed:');
            foreach ($errors as $error) {
                $this->line("  - {$error}");
            }
            return 1;
        }

        if ($this->secretManager->rotate($key, $value, $environment)) {
            $this->info("Secret '{$key}' rotated successfully for environment '{$environment}'");
            $this->warn('Remember to update your environment variables and restart the application');
            return 0;
        } else {
            $this->error("Failed to rotate secret '{$key}'");
            return 1;
        }
    }

    /**
     * Sync secrets from environment variables
     */
    protected function syncSecrets(): int
    {
        $this->info('Syncing secrets from environment variables...');
        
        $synced = $this->secretManager->syncFromEnvironment();

        if (!empty($synced)) {
            $this->info('Successfully synced secrets:');
            foreach ($synced as $key) {
                $this->line("  - {$key}");
            }
        } else {
            $this->warn('No secrets were synced');
        }

        return 0;
    }
}