<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Tests\DeploymentTestRunner;
use Illuminate\Support\Facades\Log;

class RunDeploymentTestsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deployment:test 
                            {--suite= : Run specific test suite (unit, integration, feature)}
                            {--validate : Validate test environment only}
                            {--coverage : Show test coverage report}
                            {--report : Generate detailed test report}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run comprehensive deployment test suite';

    protected DeploymentTestRunner $testRunner;

    public function __construct(DeploymentTestRunner $testRunner)
    {
        parent::__construct();
        $this->testRunner = $testRunner;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Deployment Test Suite');
        $this->info('========================');

        // Validate test environment first
        if ($this->option('validate')) {
            return $this->validateEnvironment();
        }

        // Show test coverage
        if ($this->option('coverage')) {
            return $this->showTestCoverage();
        }

        // Run specific test suite
        if ($suite = $this->option('suite')) {
            return $this->runSpecificSuite($suite);
        }

        // Run all tests
        return $this->runAllTests();
    }

    protected function validateEnvironment(): int
    {
        $this->info('🔍 Validating test environment...');
        
        $validation = $this->testRunner->validateTestEnvironment();
        
        $this->table(
            ['Category', 'Check', 'Status'],
            $this->formatValidationResults($validation)
        );

        $allValid = $this->areAllValidationsPassing($validation);
        
        if ($allValid) {
            $this->info('✅ Test environment is properly configured');
            return 0;
        } else {
            $this->error('❌ Test environment has configuration issues');
            return 1;
        }
    }

    protected function showTestCoverage(): int
    {
        $this->info('📊 Deployment Test Coverage');
        
        $coverage = $this->testRunner->getTestCoverage();
        
        $coverageData = [];
        foreach ($coverage as $area => $info) {
            $coverageData[] = [
                str_replace('_', ' ', ucwords($area, '_')),
                $info['covered'] ? '✅ Covered' : '❌ Not Covered',
                implode(', ', $info['tests'])
            ];
        }
        
        $this->table(['Area', 'Status', 'Tests'], $coverageData);
        
        $totalAreas = count($coverage);
        $coveredAreas = count(array_filter($coverage, fn($area) => $area['covered']));
        $coveragePercentage = round(($coveredAreas / $totalAreas) * 100, 1);
        
        $this->info("📈 Overall Coverage: {$coveragePercentage}% ({$coveredAreas}/{$totalAreas} areas covered)");
        
        return 0;
    }

    protected function runSpecificSuite(string $suite): int
    {
        $this->info("🧪 Running {$suite} test suite...");
        
        try {
            $results = $this->testRunner->runSpecificTestSuite($suite);
            $this->displaySuiteResults($results);
            
            return $results['failed'] === 0 ? 0 : 1;
            
        } catch (\InvalidArgumentException $e) {
            $this->error("❌ {$e->getMessage()}");
            $this->info('Available suites: unit, integration, feature');
            return 1;
        }
    }

    protected function runAllTests(): int
    {
        $this->info('🧪 Running comprehensive deployment test suite...');
        $this->newLine();
        
        $results = $this->testRunner->runAllTests();
        
        // Display results for each suite
        foreach ($results as $suiteKey => $suite) {
            $this->displaySuiteResults($suite);
            $this->newLine();
        }
        
        // Display overall summary
        $this->displayOverallSummary($results);
        
        // Check if all tests passed
        $allPassed = true;
        foreach ($results as $suite) {
            if ($suite['failed'] > 0) {
                $allPassed = false;
                break;
            }
        }
        
        if ($this->option('report')) {
            $this->info('📄 Detailed test report generated in storage/logs/deployment-test-report.json');
        }
        
        return $allPassed ? 0 : 1;
    }

    protected function displaySuiteResults(array $suite): void
    {
        $status = $suite['failed'] === 0 ? '✅' : '❌';
        $this->info("{$status} {$suite['name']} - {$suite['passed']}/{$suite['total']} passed ({$suite['duration']}s)");
        
        if ($suite['failed'] > 0) {
            foreach ($suite['tests'] as $test) {
                if (!$test['passed']) {
                    $this->warn("  ❌ {$test['class']}");
                    foreach ($test['errors'] as $error) {
                        $this->line("     - {$error}");
                    }
                }
            }
        }
    }

    protected function displayOverallSummary(array $results): void
    {
        $totalTests = 0;
        $totalPassed = 0;
        $totalFailed = 0;
        $totalDuration = 0;

        foreach ($results as $suite) {
            $totalTests += $suite['total'];
            $totalPassed += $suite['passed'];
            $totalFailed += $suite['failed'];
            $totalDuration += $suite['duration'];
        }

        $successRate = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 1) : 0;
        
        $this->info('📊 Overall Summary');
        $this->info('==================');
        $this->info("Total Tests: {$totalTests}");
        $this->info("Passed: {$totalPassed}");
        $this->info("Failed: {$totalFailed}");
        $this->info("Success Rate: {$successRate}%");
        $this->info("Total Duration: " . round($totalDuration, 2) . "s");
        
        if ($totalFailed === 0) {
            $this->info('🎉 All deployment tests passed!');
        } else {
            $this->error("💥 {$totalFailed} test(s) failed");
        }
    }

    protected function formatValidationResults(array $validation): array
    {
        $results = [];
        
        foreach ($validation as $category => $checks) {
            foreach ($checks as $check => $status) {
                $results[] = [
                    ucfirst($category),
                    str_replace('_', ' ', ucwords($check, '_')),
                    $status ? '✅ Pass' : '❌ Fail'
                ];
            }
        }
        
        return $results;
    }

    protected function areAllValidationsPassing(array $validation): bool
    {
        foreach ($validation as $category => $checks) {
            foreach ($checks as $status) {
                if (!$status) {
                    return false;
                }
            }
        }
        
        return true;
    }
}