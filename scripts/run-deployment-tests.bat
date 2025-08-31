@echo off
REM Deployment Test Runner Script for Windows
REM This script runs the comprehensive deployment test suite
REM and generates reports for CI/CD integration

setlocal enabledelayedexpansion

REM Configuration
set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%.."
set "TEST_RESULTS_DIR=%PROJECT_ROOT%\storage\logs"
set "PHPUNIT_CONFIG=%PROJECT_ROOT%\phpunit.deployment.xml"

REM Default values
set "RUN_VALIDATION=false"
set "RUN_COVERAGE=false"
set "GENERATE_REPORT=false"
set "TEST_SUITE="
set "VERBOSE=false"
set "EXIT_ON_FAILURE=true"

REM Parse command line arguments
:parse_args
if "%~1"=="" goto :args_done
if "%~1"=="-s" (
    set "TEST_SUITE=%~2"
    shift
    shift
    goto :parse_args
)
if "%~1"=="--suite" (
    set "TEST_SUITE=%~2"
    shift
    shift
    goto :parse_args
)
if "%~1"=="-v" (
    set "RUN_VALIDATION=true"
    shift
    goto :parse_args
)
if "%~1"=="--validate" (
    set "RUN_VALIDATION=true"
    shift
    goto :parse_args
)
if "%~1"=="-c" (
    set "RUN_COVERAGE=true"
    shift
    goto :parse_args
)
if "%~1"=="--coverage" (
    set "RUN_COVERAGE=true"
    shift
    goto :parse_args
)
if "%~1"=="-r" (
    set "RUN_REPORT=true"
    shift
    goto :parse_args
)
if "%~1"=="--report" (
    set "GENERATE_REPORT=true"
    shift
    goto :parse_args
)
if "%~1"=="--verbose" (
    set "VERBOSE=true"
    shift
    goto :parse_args
)
if "%~1"=="--no-exit-on-failure" (
    set "EXIT_ON_FAILURE=false"
    shift
    goto :parse_args
)
if "%~1"=="-h" (
    goto :show_usage
)
if "%~1"=="--help" (
    goto :show_usage
)
echo Unknown option: %~1
goto :show_usage

:args_done

echo.
echo 🚀 Deployment Test Runner
echo =========================

REM Check prerequisites
echo 🔍 Checking prerequisites...

if not exist "%PROJECT_ROOT%\artisan" (
    echo ❌ Error: Not in Laravel project root
    exit /b 1
)

if not exist "%PHPUNIT_CONFIG%" (
    echo ❌ Error: PHPUnit deployment config not found: %PHPUNIT_CONFIG%
    exit /b 1
)

if not exist "%PROJECT_ROOT%\vendor" (
    echo ❌ Error: Vendor directory not found. Run 'composer install' first.
    exit /b 1
)

REM Create test results directory
if not exist "%TEST_RESULTS_DIR%" mkdir "%TEST_RESULTS_DIR%"

echo ✅ Prerequisites check passed

REM Setup test environment
echo 🔧 Setting up test environment...

cd /d "%PROJECT_ROOT%"

set "APP_ENV=testing"
set "DB_CONNECTION=sqlite"
set "DB_DATABASE=:memory:"
set "CACHE_DRIVER=array"
set "QUEUE_CONNECTION=sync"
set "MAIL_MAILER=array"
set "DEPLOYMENT_TESTING=true"
set "SENTRY_LARAVEL_DSN="
set "FLAGSMITH_ENVIRONMENT_KEY="
set "GRAFANA_CLOUD_API_KEY="

echo ✅ Test environment configured

REM Run validation if requested
if "%RUN_VALIDATION%"=="true" (
    echo 🔍 Validating test environment...
    php artisan deployment:test --validate
    if !errorlevel! neq 0 (
        echo ❌ Test environment validation failed
        exit /b 1
    )
    echo ✅ Test environment validation passed
    goto :end
)

REM Run tests
if "%RUN_COVERAGE%"=="true" (
    echo 📊 Running tests with coverage...
    set "COVERAGE_DIR=%TEST_RESULTS_DIR%\coverage"
    if not exist "!COVERAGE_DIR!" mkdir "!COVERAGE_DIR!"
    
    .\vendor\bin\phpunit --configuration "%PHPUNIT_CONFIG%" --coverage-html "!COVERAGE_DIR!" --coverage-clover "!COVERAGE_DIR!\clover.xml" --log-junit "%TEST_RESULTS_DIR%\junit.xml"
    if !errorlevel! neq 0 (
        echo ❌ Tests with coverage failed
        if "%EXIT_ON_FAILURE%"=="true" exit /b 1
    ) else (
        echo ✅ Tests with coverage completed
        echo 📄 Coverage report: !COVERAGE_DIR!\index.html
    )
) else if not "%TEST_SUITE%"=="" (
    echo 🧪 Running %TEST_SUITE% test suite...
    set "CMD=php artisan deployment:test --suite=%TEST_SUITE%"
    if "%GENERATE_REPORT%"=="true" set "CMD=!CMD! --report"
    if "%VERBOSE%"=="true" set "CMD=!CMD! --verbose"
    
    !CMD!
    if !errorlevel! neq 0 (
        echo ❌ %TEST_SUITE% test suite failed
        if "%EXIT_ON_FAILURE%"=="true" exit /b 1
    ) else (
        echo ✅ %TEST_SUITE% test suite passed
    )
) else (
    echo 🧪 Running comprehensive deployment test suite...
    set "CMD=php artisan deployment:test"
    if "%GENERATE_REPORT%"=="true" set "CMD=!CMD! --report"
    if "%VERBOSE%"=="true" set "CMD=!CMD! --verbose"
    
    !CMD!
    if !errorlevel! neq 0 (
        echo ❌ Some deployment tests failed
        if "%EXIT_ON_FAILURE%"=="true" exit /b 1
    ) else (
        echo ✅ All deployment tests passed
    )
)

REM Generate report if requested
if "%GENERATE_REPORT%"=="true" (
    echo 📄 Generating test report...
    set "REPORT_FILE=%TEST_RESULTS_DIR%\deployment-test-report.json"
    if exist "!REPORT_FILE!" (
        echo ✅ Test report generated: !REPORT_FILE!
    ) else (
        echo ⚠️  Test report not found
    )
)

REM Show coverage report if available
if "%RUN_COVERAGE%"=="true" (
    php artisan deployment:test --coverage
)

echo 🎉 Test execution completed!
goto :end

:show_usage
echo Usage: %~nx0 [OPTIONS]
echo.
echo Options:
echo   -s, --suite SUITE     Run specific test suite (unit, integration, feature)
echo   -v, --validate        Validate test environment only
echo   -c, --coverage        Generate test coverage report
echo   -r, --report          Generate detailed test report
echo   --verbose             Enable verbose output
echo   --no-exit-on-failure  Don't exit on test failures
echo   -h, --help            Show this help message
echo.
echo Examples:
echo   %~nx0                    # Run all deployment tests
echo   %~nx0 -s unit           # Run only unit tests
echo   %~nx0 -v                # Validate environment
echo   %~nx0 -c -r             # Run tests with coverage and report
exit /b 0

:end
echo 🧹 Cleanup completed
endlocal