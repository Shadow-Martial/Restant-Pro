<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SentryService;
use App\Services\SentryPerformanceService;

class TestSentryIntegration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sentry:test {--performance : Test performance monitoring}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Sentry integration and monitoring';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing Sentry integration...');

        // Test basic Sentry service
        $sentryService = app(SentryService::class);
        $results = $sentryService->testIntegration();

        $this->displayResults($results);

        // Test performance monitoring if requested
        if ($this->option('performance')) {
            $this->testPerformanceMonitoring();
        }

        return $results['overall_success'] ? 0 : 1;
    }

    /**
     * Display test results.
     */
    protected function displayResults(array $results): void
    {
        $this->info('Sentry Integration Test Results:');
        $this->line('');

        foreach ($results as $test => $result) {
            if ($test === 'overall_success') {
                continue;
            }

            if (is_array($result)) {
                $status = $result['success'] ? '✅' : '❌';
                $this->line("{$status} {$test}: " . ($result['success'] ? 'PASSED' : 'FAILED'));
                
                if (isset($result['event_id'])) {
                    $this->line("   Event ID: {$result['event_id']}");
                }
            }
        }

        $this->line('');
        $overallStatus = $results['overall_success'] ? '✅ PASSED' : '❌ FAILED';
        $this->line("Overall Status: {$overallStatus}");

        if (!$results['overall_success'] && isset($results['error'])) {
            $this->error("Error: {$results['error']}");
        }
    }

    /**
     * Test performance monitoring.
     */
    protected function testPerformanceMonitoring(): void
    {
        $this->info('');
        $this->info('Testing performance monitoring...');

        $performanceService = app(SentryPerformanceService::class);

        // Test transaction tracking
        $transactionId = $performanceService->startTransaction(
            'test-transaction',
            'test.operation',
            ['test' => true]
        );

        if ($transactionId) {
            $this->line('✅ Transaction started successfully');
            
            // Simulate some work
            usleep(100000); // 100ms
            
            $performanceService->finishTransaction($transactionId, [
                'test_completed' => true,
            ]);
            
            $this->line('✅ Transaction finished successfully');
        } else {
            $this->line('❌ Failed to start transaction');
        }

        // Test cache operation tracking
        $performanceService->trackCacheOperation('get', 'test-key', false);
        $this->line('✅ Cache operation tracked');

        $this->info('Performance monitoring test completed');
    }
}