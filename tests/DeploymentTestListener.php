<?php

namespace Tests;

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestListenerDefaultImplementation;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Warning;
use Illuminate\Support\Facades\Log;

class DeploymentTestListener implements TestListener
{
    use TestListenerDefaultImplementation;

    protected array $testResults = [];
    protected array $suiteResults = [];
    protected float $suiteStartTime;
    protected float $testStartTime;

    public function startTestSuite(TestSuite $suite): void
    {
        $this->suiteStartTime = microtime(true);
        
        Log::info("Starting test suite: {$suite->getName()}");
        
        $this->suiteResults[$suite->getName()] = [
            'name' => $suite->getName(),
            'tests' => 0,
            'assertions' => 0,
            'failures' => 0,
            'errors' => 0,
            'warnings' => 0,
            'skipped' => 0,
            'start_time' => $this->suiteStartTime,
            'duration' => 0
        ];
    }

    public function endTestSuite(TestSuite $suite): void
    {
        $duration = microtime(true) - $this->suiteStartTime;
        $this->suiteResults[$suite->getName()]['duration'] = $duration;
        
        $result = $this->suiteResults[$suite->getName()];
        
        Log::info("Completed test suite: {$suite->getName()}", [
            'duration' => round($duration, 3),
            'tests' => $result['tests'],
            'failures' => $result['failures'],
            'errors' => $result['errors']
        ]);

        // Generate suite-specific report
        $this->generateSuiteReport($suite->getName(), $result);
    }

    public function startTest(Test $test): void
    {
        $this->testStartTime = microtime(true);
        
        $testName = $this->getTestName($test);
        Log::debug("Starting test: {$testName}");
        
        $this->testResults[$testName] = [
            'name' => $testName,
            'status' => 'running',
            'start_time' => $this->testStartTime,
            'duration' => 0,
            'assertions' => 0,
            'memory_usage' => memory_get_usage(true),
            'errors' => [],
            'warnings' => []
        ];
    }

    public function endTest(Test $test, float $time): void
    {
        $testName = $this->getTestName($test);
        $duration = microtime(true) - $this->testStartTime;
        
        if (isset($this->testResults[$testName])) {
            $this->testResults[$testName]['duration'] = $duration;
            $this->testResults[$testName]['memory_usage'] = memory_get_usage(true) - $this->testResults[$testName]['memory_usage'];
            
            if ($this->testResults[$testName]['status'] === 'running') {
                $this->testResults[$testName]['status'] = 'passed';
            }
        }

        // Update suite statistics
        $suiteName = $this->getSuiteName($test);
        if (isset($this->suiteResults[$suiteName])) {
            $this->suiteResults[$suiteName]['tests']++;
        }

        Log::debug("Completed test: {$testName}", [
            'duration' => round($duration, 3),
            'status' => $this->testResults[$testName]['status'] ?? 'unknown',
            'memory_usage' => $this->formatBytes($this->testResults[$testName]['memory_usage'] ?? 0)
        ]);
    }

    public function addError(Test $test, \Throwable $e, float $time): void
    {
        $testName = $this->getTestName($test);
        
        if (isset($this->testResults[$testName])) {
            $this->testResults[$testName]['status'] = 'error';
            $this->testResults[$testName]['errors'][] = [
                'type' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }

        // Update suite statistics
        $suiteName = $this->getSuiteName($test);
        if (isset($this->suiteResults[$suiteName])) {
            $this->suiteResults[$suiteName]['errors']++;
        }

        Log::error("Test error in {$testName}: {$e->getMessage()}", [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $testName = $this->getTestName($test);
        
        if (isset($this->testResults[$testName])) {
            $this->testResults[$testName]['status'] = 'failed';
            $this->testResults[$testName]['errors'][] = [
                'type' => 'failure',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
        }

        // Update suite statistics
        $suiteName = $this->getSuiteName($test);
        if (isset($this->suiteResults[$suiteName])) {
            $this->suiteResults[$suiteName]['failures']++;
        }

        Log::warning("Test failure in {$testName}: {$e->getMessage()}", [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $testName = $this->getTestName($test);
        
        if (isset($this->testResults[$testName])) {
            $this->testResults[$testName]['warnings'][] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }

        // Update suite statistics
        $suiteName = $this->getSuiteName($test);
        if (isset($this->suiteResults[$suiteName])) {
            $this->suiteResults[$suiteName]['warnings']++;
        }

        Log::warning("Test warning in {$testName}: {$e->getMessage()}");
    }

    public function addIncompleteTest(Test $test, \Throwable $e, float $time): void
    {
        $testName = $this->getTestName($test);
        
        if (isset($this->testResults[$testName])) {
            $this->testResults[$testName]['status'] = 'incomplete';
            $this->testResults[$testName]['errors'][] = [
                'type' => 'incomplete',
                'message' => $e->getMessage()
            ];
        }

        Log::info("Test incomplete: {$testName} - {$e->getMessage()}");
    }

    public function addRiskyTest(Test $test, \Throwable $e, float $time): void
    {
        $testName = $this->getTestName($test);
        
        if (isset($this->testResults[$testName])) {
            $this->testResults[$testName]['status'] = 'risky';
            $this->testResults[$testName]['warnings'][] = [
                'type' => 'risky',
                'message' => $e->getMessage()
            ];
        }

        Log::warning("Risky test: {$testName} - {$e->getMessage()}");
    }

    public function addSkippedTest(Test $test, \Throwable $e, float $time): void
    {
        $testName = $this->getTestName($test);
        
        if (isset($this->testResults[$testName])) {
            $this->testResults[$testName]['status'] = 'skipped';
            $this->testResults[$testName]['errors'][] = [
                'type' => 'skipped',
                'message' => $e->getMessage()
            ];
        }

        // Update suite statistics
        $suiteName = $this->getSuiteName($test);
        if (isset($this->suiteResults[$suiteName])) {
            $this->suiteResults[$suiteName]['skipped']++;
        }

        Log::info("Test skipped: {$testName} - {$e->getMessage()}");
    }

    protected function getTestName(Test $test): string
    {
        if (method_exists($test, 'getName')) {
            return get_class($test) . '::' . $test->getName();
        }
        
        return get_class($test);
    }

    protected function getSuiteName(Test $test): string
    {
        $className = get_class($test);
        
        if (strpos($className, 'Unit') !== false) {
            return 'Unit Tests';
        } elseif (strpos($className, 'Integration') !== false) {
            return 'Integration Tests';
        } elseif (strpos($className, 'Feature') !== false) {
            return 'Feature Tests';
        }
        
        return 'Unknown Suite';
    }

    protected function generateSuiteReport(string $suiteName, array $result): void
    {
        $reportPath = storage_path("logs/test-suite-{$this->sanitizeFilename($suiteName)}-" . date('Y-m-d-H-i-s') . '.json');
        
        $report = [
            'suite' => $suiteName,
            'summary' => $result,
            'tests' => array_filter($this->testResults, function($test) use ($suiteName) {
                return $this->getSuiteNameFromTestName($test['name']) === $suiteName;
            }),
            'timestamp' => now()->toISOString()
        ];

        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        Log::info("Suite report generated: {$reportPath}");
    }

    protected function getSuiteNameFromTestName(string $testName): string
    {
        if (strpos($testName, 'Unit') !== false) {
            return 'Unit Tests';
        } elseif (strpos($testName, 'Integration') !== false) {
            return 'Integration Tests';
        } elseif (strpos($testName, 'Feature') !== false) {
            return 'Feature Tests';
        }
        
        return 'Unknown Suite';
    }

    protected function sanitizeFilename(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9-_]/', '-', strtolower($filename));
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function getTestResults(): array
    {
        return $this->testResults;
    }

    public function getSuiteResults(): array
    {
        return $this->suiteResults;
    }
}