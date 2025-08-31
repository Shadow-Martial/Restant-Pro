<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;
use function Sentry\captureException;
use function Sentry\captureMessage;
use function Sentry\configureScope;
use function Sentry\withScope;
use Sentry\Severity;
use Sentry\State\Scope;

class SentryService
{
    /**
     * Capture an exception with tenant context.
     */
    public function captureException(Throwable $exception, array $context = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        return withScope(function (Scope $scope) use ($exception, $context): ?string {
            $this->addTenantContext($scope, $context);
            return captureException($exception);
        });
    }

    /**
     * Capture a message with tenant context.
     */
    public function captureMessage(string $message, string $level = 'info', array $context = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $severity = $this->mapLogLevelToSeverity($level);

        return withScope(function (Scope $scope) use ($message, $severity, $context): ?string {
            $this->addTenantContext($scope, $context);
            return captureMessage($message, $severity);
        });
    }

    /**
     * Capture a business logic error with tenant context.
     */
    public function captureBusinessError(string $message, array $context = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        return withScope(function (Scope $scope) use ($message, $context): ?string {
            $scope->setTag('error_type', 'business_logic');
            $this->addTenantContext($scope, $context);
            
            // Add business context
            if (isset($context['order_id'])) {
                $scope->setTag('order_id', $context['order_id']);
            }
            
            if (isset($context['restaurant_id'])) {
                $scope->setTag('restaurant_id', $context['restaurant_id']);
            }
            
            return captureMessage($message, Severity::error());
        });
    }

    /**
     * Capture a performance issue.
     */
    public function capturePerformanceIssue(string $operation, float $duration, array $context = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        return withScope(function (Scope $scope) use ($operation, $duration, $context): ?string {
            $scope->setTag('performance_issue', true);
            $scope->setTag('operation', $operation);
            $scope->setTag('duration_ms', $duration);
            
            $this->addTenantContext($scope, $context);
            
            $message = "Performance issue detected: {$operation} took {$duration}ms";
            return captureMessage($message, Severity::warning());
        });
    }

    /**
     * Add deployment release information.
     */
    public function addDeploymentContext(string $version, string $environment): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        configureScope(function (Scope $scope) use ($version, $environment): void {
            $scope->setTag('deployment_version', $version);
            $scope->setTag('deployment_environment', $environment);
            $scope->setContext('deployment', [
                'version' => $version,
                'environment' => $environment,
                'timestamp' => now()->toISOString(),
            ]);
        });
    }

    /**
     * Add tenant-specific context to Sentry scope.
     */
    protected function addTenantContext(Scope $scope, array $context = []): void
    {
        // Add deployment environment
        $deploymentEnv = app('deployment.environment');
        $scope->setTag('deployment_environment', $deploymentEnv);

        // Add tenant information from context
        if (isset($context['tenant_id'])) {
            $scope->setTag('tenant_id', $context['tenant_id']);
        }

        if (isset($context['restaurant_id'])) {
            $scope->setTag('restaurant_id', $context['restaurant_id']);
        }

        if (isset($context['subdomain'])) {
            $scope->setTag('tenant_subdomain', $context['subdomain']);
        }

        // Add user context if authenticated and not already set
        if (Auth::check() && !isset($context['skip_user_context'])) {
            $user = Auth::user();
            $scope->setUser([
                'id' => $user->id,
                'email' => $user->email ?? null,
            ]);

            // Add user's restaurant if available
            if (isset($user->restaurant_id)) {
                $scope->setTag('user_restaurant_id', $user->restaurant_id);
            }
        }

        // Add request context if available
        if (request()) {
            $scope->setContext('request_context', [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
                'ip' => request()->ip(),
            ]);
        }

        // Add custom context data
        if (!empty($context['extra'])) {
            $scope->setContext('extra', $context['extra']);
        }

        // Add tags from context
        if (!empty($context['tags'])) {
            foreach ($context['tags'] as $key => $value) {
                $scope->setTag($key, $value);
            }
        }
    }

    /**
     * Map Laravel log levels to Sentry severity.
     */
    protected function mapLogLevelToSeverity(string $level): Severity
    {
        return match (strtolower($level)) {
            'emergency', 'alert', 'critical', 'error' => Severity::error(),
            'warning' => Severity::warning(),
            'notice', 'info' => Severity::info(),
            'debug' => Severity::debug(),
            default => Severity::info(),
        };
    }

    /**
     * Check if Sentry is enabled.
     */
    protected function isEnabled(): bool
    {
        return config('deployment.monitoring.sentry.enabled', false) && 
               !empty(config('sentry.dsn'));
    }

    /**
     * Test Sentry integration.
     */
    public function testIntegration(): array
    {
        $results = [];

        try {
            // Test basic message capture
            $messageId = $this->captureMessage('Sentry integration test', 'info', [
                'test' => true,
                'timestamp' => now()->toISOString(),
            ]);
            
            $results['message_capture'] = [
                'success' => !empty($messageId),
                'event_id' => $messageId,
            ];

            // Test exception capture
            try {
                throw new \Exception('Test exception for Sentry integration');
            } catch (\Exception $e) {
                $exceptionId = $this->captureException($e, [
                    'test' => true,
                    'test_type' => 'exception_capture',
                ]);
                
                $results['exception_capture'] = [
                    'success' => !empty($exceptionId),
                    'event_id' => $exceptionId,
                ];
            }

            $results['overall_success'] = true;
            
        } catch (\Exception $e) {
            $results['overall_success'] = false;
            $results['error'] = $e->getMessage();
            
            Log::error('Sentry integration test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $results;
    }
}