<?php

namespace Tests\Commands;

use Illuminate\Console\Command;
use Tests\DeploymentTestRunner;
use Illuminate\Support\Facades\Log;

class RunDeploymentTestsCommand extends Command
{
    protected $signature = 'deployment:test 
                            {--suite= : Run specific test suite (unit|integration|feature|all)}
                            {--scenario= : Run specific deployment scenario}
                            {--report : Generate detailed report}
                            {--coverage : Include coverage analysis}
                            {--verbose : Show detailed output}';

    protected $description = 'Run comprehensive deployment test suite';

    protected DeploymentTestRunner $testRunner;

    public function __construct(DeploymentTestRunner $testRunner)
    {
        parent::__construct();
        $this->testRunner = $testRunner;
    }

    public function handle(): int
    {
        $this->info('🚀 Starting Deployment Test Suite');
        $this->newLine();

        $suite = $this->option('suite') ?? 'all';
        $scenario = $this->option('scenario');
        $generateReport = $this->option('report');
        $includeCoverage = $this->option('coverage');
        $verbose = $this->option('verbose');

        try {
            if ($scenario) {
                return $this->runSpecificScenario($scenario, $verbose);
            }

            if ($suite === 'all') {
                return $this->runAllTests($generateReport, $includeCoverage, $verbose);
            }

            return $this->runSpecificSuite($suite, $verbose);

        } catch (\Exception $e) {
            $this->error("Test execution failed: {$e->getMessage()}");
            Log::error('Deployment test execution failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    protected function runAllTests(bool $generateReport, bool $includeCoverage, bool $verbose): int
    {
        $this->info('Running all deployment tests...');
        $this->newLine();

        // Validate test environment first
        $this->validateEnvironment();

        // Run all test suites
        $results = $this->testRunner->runAllTests();
        
        // Run deployment scenarios
        $scenarioResults = $this->testRunner->runDeploymentScenarioTests();

        // Display results
        $this->displayResults($results, $verbose);
        $this->displayScenarioResults($scenarioResults, $verbose);

        // Generate comprehensive report if requested
        if ($generateReport) {
            $this->generateReport($includeCoverage);
        }

        // Determine exit code
        $totalFailed = array_sum(array_column($results, 'failed'));
        $scenariosFailed = $scenarioResults['failed'];

        if ($totalFailed > 0 || $scenariosFailed > 0) {
            $this->error("❌ Tests failed: {$totalFailed} test failures, {$scenariosFailed} scenario failures");
            return 1;
        }

        $this->info('✅ All tests passed successfully!');
        return 0;
    }

    protected function runSpecificSuite(string $suite, bool $verbose): int
    {
        $this->info("Running {$suite} test suite...");
        $this->newLine();

        $results = $this->testRunner->runSpecificTestSuite($suite);
        
        $this->displaySuiteResult($results, $verbose);

        if ($results['failed'] > 0) {
            $this->error("❌ {$suite} suite failed: {$results['failed']} failures");
            return 1;
        }

        $this->info("✅ {$suite} suite passed successfully!");
        return 0;
    }

    protected function runSpecificScenario(string $scenario, bool $verbose): int
    {
        $this->info("Running deployment scenario: {$scenario}");
        $this->newLine();

        $scenarioResults = $this->testRunner->runDeploymentScenarioTests();
        
        if (!isset($scenarioResults['scenarios'][$scenario])) {
            $this->error("Scenario '{$scenario}' not found");
            return 1;
        }

        $result = $scenarioResults['scenarios'][$scenario];
        
        $this->displayScenarioResult($scenario, $result, $verbose);

        if (!$result['passed']) {
            $this->error("❌ Scenario '{$scenario}' failed");
            return 1;
        }

        $this->info("✅ Scenario '{$scenario}' passed successfully!");
        return 0;
    }

    protected function validateEnvironment(): void
    {
        $this->info('🔍 Validating test environment...');
        
        $validation = $this->testRunner->validateTestEnvironment();
        
        $issues = [];
        foreach ($validation as $category => $checks) {
            foreach ($checks as $check => $passed) {
                if (!$passed) {
                    $issues[] = "{$category}.{$check}";
                }
            }
        }

        if (!empty($issues)) {
            $this->warn('⚠️  Environment validation issues found:');
            foreach ($issues as $issue) {
                $this->line("  - {$issue}");
            }
            $this->newLine();
        } else {
            $this->info('✅ Environment validation passed');
        }
        
        $this->newLine();
    }

    protected function displayResults(array $results, bool $verbose): void
    {
        $this->info('📊 Test Suite Results:');
        $this->newLine();

        foreach ($results as $suiteKey => $suite) {
            $status = $suite['failed'] > 0 ? '❌' : '✅';
            $this->line("{$status} {$suite['name']}: {$suite['passed']}/{$suite['total']} passed ({$suite['duration']}s)");
            
            if ($verbose && $suite['failed'] > 0) {
                foreach ($suite['tests'] as $test) {
                    if (!$test['passed']) {
                        $this->line("    ❌ {$test['class']}");
                        foreach ($test['errors'] as $error) {
                            $this->line("      - {$error}");
                        }
                    }
                }
            }
        }
        
        $this->newLine();
    }

    protected function displaySuiteResult(array $result, bool $verbose): void
    {
        $status = $result['failed'] > 0 ? '❌' : '✅';
        $this->line("{$status} {$result['name']}: {$result['passed']}/{$result['total']} passed ({$result['duration']}s)");
        
        if ($verbose) {
            foreach ($result['tests'] as $test) {
                $testStatus = $test['passed'] ? '✅' : '❌';
                $this->line("  {$testStatus} {$test['class']} ({$test['duration']}s)");
                
                if (!$test['passed']) {
                    foreach ($test['errors'] as $error) {
                        $this->line("    - {$error}");
                    }
                }
            }
        }
    }

    protected function displayScenarioResults(array $scenarioResults, bool $verbose): void
    {
        $this->info('🎭 Deployment Scenario Results:');
        $this->newLine();

        foreach ($scenarioResults['scenarios'] as $scenario => $result) {
            $this->displayScenarioResult($scenario, $result, $verbose);
        }
        
        $this->newLine();
    }

    protected function displayScenarioResult(string $scenario, array $result, bool $verbose): void
    {
        $status = $result['passed'] ? '✅' : '❌';
        $this->line("{$status} {$scenario}: " . ($result['passed'] ? 'PASSED' : 'FAILED') . " ({$result['duration']}s)");
        
        if ($verbose && isset($result['steps'])) {
            foreach ($result['steps'] as $step => $stepResult) {
                $stepStatus = $stepResult ? '✅' : '❌';
                $this->line("    {$stepStatus} {$step}");
            }
        }
        
        if (!$result['passed'] && !empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                $this->line("    ❌ {$error}");
            }
        }
    }

    protected function generateReport(bool $includeCoverage): void
    {
        $this->info('📋 Generating comprehensive test report...');
        
        $report = $this->testRunner->generateComprehensiveReport();
        
        // Save report to file
        $reportPath = storage_path('logs/deployment-test-report-' . date('Y-m-d-H-i-s') . '.json');
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        $this->info("📄 Detailed report saved to: {$reportPath}");
        
        // Display summary
        $this->newLine();
        $this->info('📈 Test Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Tests', $report['summary']['total_tests']],
                ['Passed', $report['summary']['passed']],
                ['Failed', $report['summary']['failed']],
                ['Success Rate', $report['summary']['success_rate'] . '%'],
                ['Total Duration', $report['summary']['total_duration'] . 's'],
                ['Total Assertions', $report['summary']['total_assertions']],
                ['Scenarios Tested', $report['summary']['scenarios_tested']],
                ['Scenarios Passed', $report['summary']['scenarios_passed']]
            ]
        );
        
        // Display recommendations
        if (!empty($report['recommendations'])) {
            $this->newLine();
            $this->warn('💡 Recommendations:');
            foreach ($report['recommendations'] as $recommendation) {
                $this->line("  - {$recommendation}");
            }
        }
        
        // Display coverage if requested
        if ($includeCoverage) {
            $this->displayCoverage($report['coverage']);
        }
    }

    protected function displayCoverage(array $coverage): void
    {
        $this->newLine();
        $this->info('📊 Test Coverage:');
        
        foreach ($coverage as $area => $info) {
            $status = $info['covered'] ? '✅' : '❌';
            $this->line("{$status} {$area}: " . ($info['covered'] ? 'COVERED' : 'NOT COVERED'));
            
            if (isset($info['tests'])) {
                foreach ($info['tests'] as $test) {
                    $this->line("    - {$test}");
                }
            }
        }
    }
}