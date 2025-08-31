<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class SecretManager
{
    /**
     * Secret storage disk
     */
    protected string $disk = 'local';

    /**
     * Secret storage path
     */
    protected string $secretsPath = 'secrets';

    /**
     * Encryption key for secrets
     */
    protected ?string $encryptionKey = null;

    public function __construct()
    {
        $this->encryptionKey = config('app.key');
    }

    /**
     * Store a secret securely
     */
    public function store(string $key, string $value, string $environment = null): bool
    {
        try {
            $environment = $environment ?? config('app.env');
            $encryptedValue = Crypt::encryptString($value);
            
            $secretData = [
                'value' => $encryptedValue,
                'environment' => $environment,
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString(),
            ];

            $filename = $this->getSecretFilename($key, $environment);
            
            Storage::disk($this->disk)->put(
                "{$this->secretsPath}/{$filename}",
                json_encode($secretData, JSON_PRETTY_PRINT)
            );

            Log::info('Secret stored successfully', [
                'key' => $key,
                'environment' => $environment,
                'filename' => $filename,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to store secret', [
                'key' => $key,
                'environment' => $environment,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Retrieve a secret
     */
    public function get(string $key, string $environment = null): ?string
    {
        try {
            $environment = $environment ?? config('app.env');
            $filename = $this->getSecretFilename($key, $environment);
            $filePath = "{$this->secretsPath}/{$filename}";

            if (!Storage::disk($this->disk)->exists($filePath)) {
                return null;
            }

            $secretData = json_decode(
                Storage::disk($this->disk)->get($filePath),
                true
            );

            if (!$secretData || !isset($secretData['value'])) {
                return null;
            }

            return Crypt::decryptString($secretData['value']);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve secret', [
                'key' => $key,
                'environment' => $environment,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete a secret
     */
    public function delete(string $key, string $environment = null): bool
    {
        try {
            $environment = $environment ?? config('app.env');
            $filename = $this->getSecretFilename($key, $environment);
            $filePath = "{$this->secretsPath}/{$filename}";

            if (Storage::disk($this->disk)->exists($filePath)) {
                Storage::disk($this->disk)->delete($filePath);
                
                Log::info('Secret deleted successfully', [
                    'key' => $key,
                    'environment' => $environment,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete secret', [
                'key' => $key,
                'environment' => $environment,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * List all secrets for an environment
     */
    public function list(string $environment = null): array
    {
        try {
            $environment = $environment ?? config('app.env');
            $files = Storage::disk($this->disk)->files($this->secretsPath);
            $secrets = [];

            foreach ($files as $file) {
                $filename = basename($file);
                
                if (str_ends_with($filename, "_{$environment}.json")) {
                    $key = str_replace("_{$environment}.json", '', $filename);
                    $secretData = json_decode(
                        Storage::disk($this->disk)->get($file),
                        true
                    );

                    $secrets[$key] = [
                        'environment' => $secretData['environment'] ?? $environment,
                        'created_at' => $secretData['created_at'] ?? null,
                        'updated_at' => $secretData['updated_at'] ?? null,
                    ];
                }
            }

            return $secrets;
        } catch (\Exception $e) {
            Log::error('Failed to list secrets', [
                'environment' => $environment,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Rotate a secret (generate new value)
     */
    public function rotate(string $key, string $newValue, string $environment = null): bool
    {
        $environment = $environment ?? config('app.env');
        
        // Backup old secret
        $oldValue = $this->get($key, $environment);
        if ($oldValue) {
            $this->store("{$key}_backup", $oldValue, $environment);
        }

        // Store new secret
        return $this->store($key, $newValue, $environment);
    }

    /**
     * Validate secret format and strength
     */
    public function validateSecret(string $key, string $value): array
    {
        $errors = [];

        // Check minimum length
        if (strlen($value) < 8) {
            $errors[] = 'Secret must be at least 8 characters long';
        }

        // Check for specific key requirements
        switch ($key) {
            case 'APP_KEY':
                if (!str_starts_with($value, 'base64:') && strlen($value) !== 32) {
                    $errors[] = 'APP_KEY must be 32 characters or base64 encoded';
                }
                break;

            case 'DB_PASSWORD':
                if (strlen($value) < 12) {
                    $errors[] = 'Database password must be at least 12 characters';
                }
                break;

            case 'JWT_SECRET':
                if (strlen($value) < 32) {
                    $errors[] = 'JWT secret must be at least 32 characters';
                }
                break;
        }

        return $errors;
    }

    /**
     * Generate secure random secret
     */
    public function generateSecret(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Get secret filename
     */
    protected function getSecretFilename(string $key, string $environment): string
    {
        return "{$key}_{$environment}.json";
    }

    /**
     * Sync secrets from environment variables
     */
    public function syncFromEnvironment(array $keys = null): array
    {
        $keys = $keys ?? [
            'APP_KEY',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'MAIL_PASSWORD',
            'SENTRY_LARAVEL_DSN',
            'FLAGSMITH_ENVIRONMENT_KEY',
            'GRAFANA_CLOUD_API_KEY',
        ];

        $synced = [];
        $environment = config('app.env');

        foreach ($keys as $key) {
            $value = env($key);
            if (!empty($value)) {
                if ($this->store($key, $value, $environment)) {
                    $synced[] = $key;
                }
            }
        }

        Log::info('Secrets synced from environment', [
            'environment' => $environment,
            'synced_keys' => $synced,
        ]);

        return $synced;
    }
}