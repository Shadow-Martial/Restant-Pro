<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use function Sentry\startTransaction;
use function Sentry\getCurrentHub;
use Sentry\Tracing\TransactionContext;
use Sentry\Tracing\SpanContext;

class SentryPerformanceService
{
    protected array $activeTransactions = [];
    protected array $performanceThresholds = [];

    public function __construct()
    {
        $this->performanceThresholds = config('sentry.performance_thresholds', [
            'database_query' => 1000, // 1 second
            'http_request' => 5000,   // 5 seconds
            'cache_operation' => 100, // 100ms
            'file_operation' => 500,  // 500ms
        ]);
    }

    /**
     * Start a performance transaction.
     */
    public function startTransaction(string $name, string $operation = 'http.request', array $context = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $transactionContext = new TransactionContext();
            $transactionContext->setName($name);
            $transactionContext->setOp($operation);
            
            // Add deployment environment tag
            $transactionContext->setTag('deployment_environment', app('deployment.environment'));
            
            // Add tenant context
            if (isset($context['tenant_id'])) {
                $transactionContext->setTag('tenant_id', $context['tenant_id']);
            }
            
            if (isset($context['restaurant_id'])) {
                $transactionContext->setTag('restaurant_id', $context['restaurant_id']);
            }

            $transaction = startTransaction($transactionContext);
            
            $transactionId = uniqid('txn_');
            $this->activeTransactions[$transactionId] = [
                'transaction' => $transaction,
                'start_time' => microtime(true),
                'name' => $name,
                'operation' => $operation,
            ];

            return $transactionId;
            
        } catch (\Exception $e) {
            Log::warning('Failed to start Sentry transaction', [
                'name' => $name,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Finish a performance transaction.
     */
    public function finishTransaction(string $transactionId, array $context = []): void
    {
        if (!$this->isEnabled() || !isset($this->activeTransactions[$transactionId])) {
            return;
        }

        try {
            $transactionData = $this->activeTransactions[$transactionId];
            $transaction = $transactionData['transaction'];
            
            $duration = (microtime(true) - $transactionData['start_time']) * 1000; // Convert to milliseconds
            
            // Add performance context
            $transaction->setTag('duration_ms', round($duration, 2));
            
            // Check if duration exceeds threshold
            $threshold = $this->performanceThresholds[$transactionData['operation']] ?? null;
            if ($threshold && $duration > $threshold) {
                $transaction->setTag('performance_issue', true);
                $transaction->setTag('threshold_exceeded', true);
            }
            
            // Add additional context
            foreach ($context as $key => $value) {
                $transaction->setTag($key, $value);
            }
            
            $transaction->finish();
            unset($this->activeTransactions[$transactionId]);
            
        } catch (\Exception $e) {
            Log::warning('Failed to finish Sentry transaction', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Start a database query span.
     */
    public function startDatabaseSpan(string $query, array $bindings = []): ?string
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $hub = getCurrentHub();
            $transaction = $hub->getTransaction();
            
            if (!$transaction) {
                return null;
            }

            $spanContext = new SpanContext();
            $spanContext->setOp('db.query');
            $spanContext->setDescription($this->sanitizeQuery($query));
            
            $span = $transaction->startChild($spanContext);
            $span->setTag('db.type', config('database.default'));
            
            if (!empty($bindings)) {
                $span->setData('db.bindings_count', count($bindings));
            }

            $spanId = uniqid('span_');
            $this->activeTransactions[$spanId] = [
                'span' => $span,
                'start_time' => microtime(true),
                'type' => 'database',
            ];

            return $spanId;
            
        } catch (\Exception $e) {
            Log::warning('Failed to start database span', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Finish a database query span.
     */
    public function finishDatabaseSpan(string $spanId, int $rowCount = null): void
    {
        if (!$this->isEnabled() || !isset($this->activeTransactions[$spanId])) {
            return;
        }

        try {
            $spanData = $this->activeTransactions[$spanId];
            $span = $spanData['span'];
            
            $duration = (microtime(true) - $spanData['start_time']) * 1000;
            
            $span->setTag('duration_ms', round($duration, 2));
            
            if ($rowCount !== null) {
                $span->setTag('db.rows_affected', $rowCount);
            }
            
            // Check for slow queries
            $threshold = $this->performanceThresholds['database_query'] ?? 1000;
            if ($duration > $threshold) {
                $span->setTag('slow_query', true);
            }
            
            $span->finish();
            unset($this->activeTransactions[$spanId]);
            
        } catch (\Exception $e) {
            Log::warning('Failed to finish database span', [
                'span_id' => $spanId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track HTTP request performance.
     */
    public function trackHttpRequest(string $method, string $url, int $statusCode, float $duration): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $transactionId = $this->startTransaction(
                "{$method} {$url}",
                'http.request',
                [
                    'http.method' => $method,
                    'http.status_code' => $statusCode,
                    'http.url' => $url,
                ]
            );

            if ($transactionId) {
                // Simulate the duration by waiting, then finish
                $this->finishTransaction($transactionId, [
                    'http.status_code' => $statusCode,
                    'http.response_size' => strlen(response()->getContent() ?? ''),
                ]);
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to track HTTP request performance', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track cache operation performance.
     */
    public function trackCacheOperation(string $operation, string $key, bool $hit = null): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $hub = getCurrentHub();
            $transaction = $hub->getTransaction();
            
            if (!$transaction) {
                return;
            }

            $spanContext = new SpanContext();
            $spanContext->setOp('cache.' . $operation);
            $spanContext->setDescription("Cache {$operation}: {$key}");
            
            $span = $transaction->startChild($spanContext);
            $span->setTag('cache.key', $key);
            $span->setTag('cache.operation', $operation);
            
            if ($hit !== null) {
                $span->setTag('cache.hit', $hit ? 'true' : 'false');
            }
            
            $span->finish();
            
        } catch (\Exception $e) {
            Log::warning('Failed to track cache operation', [
                'operation' => $operation,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Setup database query monitoring.
     */
    public function setupDatabaseMonitoring(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        DB::listen(function ($query) {
            $duration = $query->time;
            
            // Track slow queries
            $threshold = $this->performanceThresholds['database_query'] ?? 1000;
            if ($duration > $threshold) {
                app(SentryService::class)->capturePerformanceIssue(
                    'slow_database_query',
                    $duration,
                    [
                        'extra' => [
                            'query' => $this->sanitizeQuery($query->sql),
                            'bindings_count' => count($query->bindings),
                            'connection' => $query->connectionName,
                        ],
                        'tags' => [
                            'query_type' => $this->getQueryType($query->sql),
                            'slow_query' => true,
                        ],
                    ]
                );
            }
        });
    }

    /**
     * Sanitize SQL query for logging.
     */
    protected function sanitizeQuery(string $query): string
    {
        // Remove sensitive data patterns
        $query = preg_replace('/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', '[CARD]', $query);
        $query = preg_replace('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', '[EMAIL]', $query);
        
        // Limit query length
        if (strlen($query) > 500) {
            $query = substr($query, 0, 500) . '...';
        }
        
        return $query;
    }

    /**
     * Get query type from SQL.
     */
    protected function getQueryType(string $sql): string
    {
        $sql = trim(strtoupper($sql));
        
        if (str_starts_with($sql, 'SELECT')) return 'SELECT';
        if (str_starts_with($sql, 'INSERT')) return 'INSERT';
        if (str_starts_with($sql, 'UPDATE')) return 'UPDATE';
        if (str_starts_with($sql, 'DELETE')) return 'DELETE';
        if (str_starts_with($sql, 'CREATE')) return 'CREATE';
        if (str_starts_with($sql, 'ALTER')) return 'ALTER';
        if (str_starts_with($sql, 'DROP')) return 'DROP';
        
        return 'OTHER';
    }

    /**
     * Check if performance monitoring is enabled.
     */
    protected function isEnabled(): bool
    {
        return config('deployment.monitoring.sentry.enabled', false) && 
               config('sentry.traces_sample_rate', 0) > 0;
    }
}