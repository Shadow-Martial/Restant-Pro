#!/bin/bash

# Deployment Test Runner Script
# This script runs the comprehensive deployment test suite
# and generates reports for CI/CD integration

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
TEST_RESULTS_DIR="$PROJECT_ROOT/storage/logs"
PHPUNIT_CONFIG="$PROJECT_ROOT/phpunit.deployment.xml"

# Default values
RUN_VALIDATION=false
RUN_COVERAGE=false
GENERATE_REPORT=false
TEST_SUITE=""
VERBOSE=false
EXIT_ON_FAILURE=true

# Function to print colored output
print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Function to print usage
usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -s, --suite SUITE     Run specific test suite (unit, integration, feature)"
    echo "  -v, --validate        Validate test environment only"
    echo "  -c, --coverage        Generate test coverage report"
    echo "  -r, --report          Generate detailed test report"
    echo "  --verbose             Enable verbose output"
    echo "  --no-exit-on-failure  Don't exit on test failures"
    echo "  -h, --help            Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                    # Run all deployment tests"
    echo "  $0 -s unit           # Run only unit tests"
    echo "  $0 -v                # Validate environment"
    echo "  $0 -c -r             # Run tests with coverage and report"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -s|--suite)
            TEST_SUITE="$2"
            shift 2
            ;;
        -v|--validate)
            RUN_VALIDATION=true
            shift
            ;;
        -c|--coverage)
            RUN_COVERAGE=true
            shift
            ;;
        -r|--report)
            GENERATE_REPORT=true
            shift
            ;;
        --verbose)
            VERBOSE=true
            shift
            ;;
        --no-exit-on-failure)
            EXIT_ON_FAILURE=false
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Function to check prerequisites
check_prerequisites() {
    print_status $BLUE "🔍 Checking prerequisites..."
    
    # Check if we're in the project root
    if [[ ! -f "$PROJECT_ROOT/artisan" ]]; then
        print_status $RED "❌ Error: Not in Laravel project root"
        exit 1
    fi
    
    # Check if PHPUnit config exists
    if [[ ! -f "$PHPUNIT_CONFIG" ]]; then
        print_status $RED "❌ Error: PHPUnit deployment config not found: $PHPUNIT_CONFIG"
        exit 1
    fi
    
    # Check if vendor directory exists
    if [[ ! -d "$PROJECT_ROOT/vendor" ]]; then
        print_status $RED "❌ Error: Vendor directory not found. Run 'composer install' first."
        exit 1
    fi
    
    # Check if storage/logs directory exists
    mkdir -p "$TEST_RESULTS_DIR"
    
    print_status $GREEN "✅ Prerequisites check passed"
}

# Function to setup test environment
setup_test_environment() {
    print_status $BLUE "🔧 Setting up test environment..."
    
    cd "$PROJECT_ROOT"
    
    # Set environment variables for testing
    export APP_ENV=testing
    export DB_CONNECTION=sqlite
    export DB_DATABASE=:memory:
    export CACHE_DRIVER=array
    export QUEUE_CONNECTION=sync
    export MAIL_MAILER=array
    
    # Disable external services for testing
    export DEPLOYMENT_TESTING=true
    export SENTRY_LARAVEL_DSN=""
    export FLAGSMITH_ENVIRONMENT_KEY=""
    export GRAFANA_CLOUD_API_KEY=""
    
    print_status $GREEN "✅ Test environment configured"
}

# Function to validate test environment
validate_environment() {
    print_status $BLUE "🔍 Validating test environment..."
    
    cd "$PROJECT_ROOT"
    
    if php artisan deployment:test --validate; then
        print_status $GREEN "✅ Test environment validation passed"
        return 0
    else
        print_status $RED "❌ Test environment validation failed"
        return 1
    fi
}

# Function to run specific test suite
run_test_suite() {
    local suite=$1
    print_status $BLUE "🧪 Running $suite test suite..."
    
    cd "$PROJECT_ROOT"
    
    local cmd="php artisan deployment:test --suite=$suite"
    if [[ "$GENERATE_REPORT" == "true" ]]; then
        cmd="$cmd --report"
    fi
    
    if [[ "$VERBOSE" == "true" ]]; then
        cmd="$cmd --verbose"
    fi
    
    if eval "$cmd"; then
        print_status $GREEN "✅ $suite test suite passed"
        return 0
    else
        print_status $RED "❌ $suite test suite failed"
        return 1
    fi
}

# Function to run all tests
run_all_tests() {
    print_status $BLUE "🧪 Running comprehensive deployment test suite..."
    
    cd "$PROJECT_ROOT"
    
    local cmd="php artisan deployment:test"
    if [[ "$GENERATE_REPORT" == "true" ]]; then
        cmd="$cmd --report"
    fi
    
    if [[ "$VERBOSE" == "true" ]]; then
        cmd="$cmd --verbose"
    fi
    
    if eval "$cmd"; then
        print_status $GREEN "✅ All deployment tests passed"
        return 0
    else
        print_status $RED "❌ Some deployment tests failed"
        return 1
    fi
}

# Function to run PHPUnit with coverage
run_with_coverage() {
    print_status $BLUE "📊 Running tests with coverage..."
    
    cd "$PROJECT_ROOT"
    
    local coverage_dir="$TEST_RESULTS_DIR/coverage"
    mkdir -p "$coverage_dir"
    
    if ./vendor/bin/phpunit \
        --configuration "$PHPUNIT_CONFIG" \
        --coverage-html "$coverage_dir" \
        --coverage-clover "$coverage_dir/clover.xml" \
        --log-junit "$TEST_RESULTS_DIR/junit.xml"; then
        
        print_status $GREEN "✅ Tests with coverage completed"
        print_status $BLUE "📄 Coverage report: $coverage_dir/index.html"
        return 0
    else
        print_status $RED "❌ Tests with coverage failed"
        return 1
    fi
}

# Function to generate test report
generate_test_report() {
    print_status $BLUE "📄 Generating test report..."
    
    local report_file="$TEST_RESULTS_DIR/deployment-test-report.json"
    
    if [[ -f "$report_file" ]]; then
        print_status $GREEN "✅ Test report generated: $report_file"
        
        # Extract summary from report
        if command -v jq &> /dev/null; then
            local summary=$(jq -r '.summary | "Tests: \(.total_tests), Passed: \(.passed), Failed: \(.failed), Success Rate: \(.success_rate)%"' "$report_file")
            print_status $BLUE "📊 Summary: $summary"
        fi
        
        return 0
    else
        print_status $YELLOW "⚠️  Test report not found"
        return 1
    fi
}

# Function to cleanup
cleanup() {
    print_status $BLUE "🧹 Cleaning up..."
    
    # Remove temporary files if any
    # (Currently no cleanup needed)
    
    print_status $GREEN "✅ Cleanup completed"
}

# Main execution
main() {
    print_status $BLUE "🚀 Deployment Test Runner"
    print_status $BLUE "========================="
    
    # Check prerequisites
    check_prerequisites
    
    # Setup test environment
    setup_test_environment
    
    local exit_code=0
    
    # Run validation if requested
    if [[ "$RUN_VALIDATION" == "true" ]]; then
        if ! validate_environment; then
            exit_code=1
        fi
        cleanup
        exit $exit_code
    fi
    
    # Run tests
    if [[ "$RUN_COVERAGE" == "true" ]]; then
        if ! run_with_coverage; then
            exit_code=1
        fi
    elif [[ -n "$TEST_SUITE" ]]; then
        if ! run_test_suite "$TEST_SUITE"; then
            exit_code=1
        fi
    else
        if ! run_all_tests; then
            exit_code=1
        fi
    fi
    
    # Generate report if requested
    if [[ "$GENERATE_REPORT" == "true" ]]; then
        generate_test_report
    fi
    
    # Show coverage report if available
    if [[ "$RUN_COVERAGE" == "true" ]]; then
        php artisan deployment:test --coverage
    fi
    
    # Cleanup
    cleanup
    
    # Final status
    if [[ $exit_code -eq 0 ]]; then
        print_status $GREEN "🎉 All tests completed successfully!"
    else
        print_status $RED "💥 Some tests failed!"
        
        if [[ "$EXIT_ON_FAILURE" == "true" ]]; then
            exit $exit_code
        fi
    fi
    
    exit $exit_code
}

# Trap to ensure cleanup on exit
trap cleanup EXIT

# Run main function
main "$@"