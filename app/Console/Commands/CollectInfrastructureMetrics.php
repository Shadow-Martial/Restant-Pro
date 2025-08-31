<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GrafanaCloudService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

class CollectInfrastructureMetrics extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'grafana:collect-metrics';

    /**
     * The console command description.
     */
    protected $description = 'Collect and send infrastructure metrics to Grafana Cloud';

    private GrafanaCloudService $grafanaService;

    public function __construct(GrafanaCloudService $grafanaService)
    {
        parent::__construct();
        $this->grafanaService = $grafanaService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('monitoring.grafana.infrastructure.enabled', true)) {
            $this->info('Grafana infrastructure monitoring is disabled');
            return 0;
        }

        $this->info('Collecting infrastructure metrics...');

        $metrics = [];

        // Collect basic infrastructure metrics
        $metrics = array_merge($metrics, $this->collectSystemMetrics());
        
        // Collect database metrics
        if (config('monitoring.grafana.infrastructure.metrics.database_connections', true)) {
            $metrics = array_merge($metrics, $this->collectDatabaseMetrics());
        }

        // Collect cache metrics
        if (config('monitoring.grafana.infrastructure.metrics.cache_metrics', true)) {
            $metrics = array_merge($metrics, $this->collectCacheMetrics());
        }

        // Collect queue metrics
        if (config('monitoring.grafana.infrastructure.metrics.queue_metrics', true)) {
            $metrics = array_merge($metrics, $this->collectQueueMetrics());
        }

        // Send all metrics
        if (!empty($metrics)) {
            $success = $this->grafanaService->sendPerformanceMetrics($metrics);
            
            if ($success) {
                $this->info('Successfully sent ' . count($metrics) . ' metrics to Grafana Cloud');
            } else {
                $this->error('Failed to send metrics to Grafana Cloud');
                return 1;
            }
        } else {
            $this->info('No metrics to send');
        }

        return 0;
    }

    /**
     * Collect system metrics
     */
    private function collectSystemMetrics(): array
    {
        $metrics = [];

        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $metrics[] = [
            'name' => 'laravel_memory_usage_bytes',
            'value' => $memoryUsage,
            'labels' => []
        ];

        $metrics[] = [
            'name' => 'laravel_memory_peak_bytes',
            'value' => $peakMemory,
            'labels' => []
        ];

        // Disk usage (if available)
        $storagePath = storage_path();
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);
            
            if ($freeBytes !== false && $totalBytes !== false) {
                $usedBytes = $totalBytes - $freeBytes;
                
                $metrics[] = [
                    'name' => 'laravel_disk_usage_bytes',
                    'value' => $usedBytes,
                    'labels' => ['path' => 'storage']
                ];

                $metrics[] = [
                    'name' => 'laravel_disk_free_bytes',
                    'value' => $freeBytes,
                    'labels' => ['path' => 'storage']
                ];
            }
        }

        return $metrics;
    }

    /**
     * Collect database metrics
     */
    private function collectDatabaseMetrics(): array
    {
        $metrics = [];

        try {
            // Get database connection info
            $connections = config('database.connections');
            
            foreach ($connections as $name => $config) {
                if ($config['driver'] === 'mysql') {
                    try {
                        $pdo = DB::connection($name)->getPdo();
                        
                        // Get connection status
                        $metrics[] = [
                            'name' => 'laravel_database_connection_status',
                            'value' => 1,
                            'labels' => ['connection' => $name]
                        ];

                        // Get process list count (active connections)
                        $processCount = DB::connection($name)->select('SHOW PROCESSLIST');
                        $metrics[] = [
                            'name' => 'laravel_database_active_connections',
                            'value' => count($processCount),
                            'labels' => ['connection' => $name]
                        ];

                    } catch (\Exception $e) {
                        $metrics[] = [
                            'name' => 'laravel_database_connection_status',
                            'value' => 0,
                            'labels' => ['connection' => $name]
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn('Failed to collect database metrics: ' . $e->getMessage());
        }

        return $metrics;
    }

    /**
     * Collect cache metrics
     */
    private function collectCacheMetrics(): array
    {
        $metrics = [];

        try {
            $cacheStore = Cache::getStore();
            
            if ($cacheStore instanceof \Illuminate\Cache\RedisStore) {
                $redis = Cache::getRedis();
                $info = $redis->info();
                
                if (isset($info['used_memory'])) {
                    $metrics[] = [
                        'name' => 'laravel_cache_memory_usage_bytes',
                        'value' => (float)$info['used_memory'],
                        'labels' => ['store' => 'redis']
                    ];
                }

                if (isset($info['connected_clients'])) {
                    $metrics[] = [
                        'name' => 'laravel_cache_connected_clients',
                        'value' => (float)$info['connected_clients'],
                        'labels' => ['store' => 'redis']
                    ];
                }

                if (isset($info['keyspace_hits']) && isset($info['keyspace_misses'])) {
                    $hits = (float)$info['keyspace_hits'];
                    $misses = (float)$info['keyspace_misses'];
                    $total = $hits + $misses;
                    
                    $hitRate = $total > 0 ? $hits / $total : 0;
                    
                    $metrics[] = [
                        'name' => 'laravel_cache_hit_rate',
                        'value' => $hitRate,
                        'labels' => ['store' => 'redis']
                    ];
                }
            }
        } catch (\Exception $e) {
            $this->warn('Failed to collect cache metrics: ' . $e->getMessage());
        }

        return $metrics;
    }

    /**
     * Collect queue metrics
     */
    private function collectQueueMetrics(): array
    {
        $metrics = [];

        try {
            // This is a basic implementation - in production you might want to use
            // Laravel Horizon or other queue monitoring tools for more detailed metrics
            
            $queueConnection = config('queue.default');
            
            $metrics[] = [
                'name' => 'laravel_queue_connection_status',
                'value' => 1,
                'labels' => ['connection' => $queueConnection]
            ];

            // If using Redis for queues, we can get more detailed metrics
            if ($queueConnection === 'redis') {
                try {
                    $redis = Queue::getRedis();
                    $queueNames = ['default']; // Add your queue names here
                    
                    foreach ($queueNames as $queueName) {
                        $queueKey = 'queues:' . $queueName;
                        $queueSize = $redis->llen($queueKey);
                        
                        $metrics[] = [
                            'name' => 'laravel_queue_size',
                            'value' => $queueSize,
                            'labels' => ['queue' => $queueName]
                        ];
                    }
                } catch (\Exception $e) {
                    // Queue metrics collection failed, but don't fail the entire command
                }
            }
        } catch (\Exception $e) {
            $this->warn('Failed to collect queue metrics: ' . $e->getMessage());
        }

        return $metrics;
    }
}