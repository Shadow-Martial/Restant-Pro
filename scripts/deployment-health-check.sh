#!/bin/bash

# Deployment Health Check Script
# This script verifies that the application is healthy after deployment

set -e

# Configuration
HEALTH_CHECK_URL="${HEALTH_CHECK_URL:-http://localhost/health}"
MAX_RETRIES="${MAX_RETRIES:-10}"
RETRY_DELAY="${RETRY_DELAY:-30}"
TIMEOUT="${TIMEOUT:-30}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to check if application is responding
check_app_response() {
    local url="$1"
    local timeout="$2"
    
    if curl -f -s --max-time "$timeout" "$url" > /dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

# Function to get health status
get_health_status() {
    local url="$1"
    local timeout="$2"
    
    curl -f -s --max-time "$timeout" "$url" 2>/dev/null || echo '{"status":"error","message":"Failed to connect"}'
}

# Function to check specific service health
check_service_health() {
    local service="$1"
    local base_url="$2"
    local timeout="$3"
    
    local service_url="${base_url}/${service}"
    local response=$(get_health_status "$service_url" "$timeout")
    local status=$(echo "$response" | jq -r '.status // "unknown"')
    
    case "$status" in
        "healthy")
            log_success "✅ $service: Healthy"
            return 0
            ;;
        "degraded")
            log_warning "⚠️  $service: Degraded but operational"
            return 0
            ;;
        "disabled")
            log_info "ℹ️  $service: Disabled"
            return 0
            ;;
        *)
            log_error "❌ $service: Unhealthy ($status)"
            return 1
            ;;
    esac
}

# Main health check function
perform_health_check() {
    local base_url="$1"
    local timeout="$2"
    
    log_info "Performing comprehensive health check..."
    
    # Check overall health
    local overall_response=$(get_health_status "$base_url" "$timeout")
    local overall_status=$(echo "$overall_response" | jq -r '.status // "unknown"')
    
    log_info "Overall application status: $overall_status"
    
    # Check individual services
    local services=("database" "sentry" "flagsmith" "grafana" "ssl")
    local failed_services=0
    
    for service in "${services[@]}"; do
        if ! check_service_health "$service" "$base_url" "$timeout"; then
            ((failed_services++))
        fi
    done
    
    # Determine if deployment should be considered successful
    case "$overall_status" in
        "healthy")
            log_success "🎉 All systems healthy - deployment successful!"
            return 0
            ;;
        "degraded")
            if [ "$failed_services" -le 2 ]; then
                log_warning "⚠️  Some services degraded but deployment acceptable"
                return 0
            else
                log_error "💥 Too many services degraded - deployment may have issues"
                return 1
            fi
            ;;
        *)
            log_error "💥 Application unhealthy - deployment failed"
            return 1
            ;;
    esac
}

# Function to wait for application to be ready
wait_for_app_ready() {
    local url="$1"
    local max_retries="$2"
    local retry_delay="$3"
    local timeout="$4"
    
    log_info "Waiting for application to be ready at $url"
    
    for ((i=1; i<=max_retries; i++)); do
        log_info "Attempt $i/$max_retries..."
        
        if check_app_response "$url" "$timeout"; then
            log_success "Application is responding!"
            return 0
        else
            if [ "$i" -lt "$max_retries" ]; then
                log_warning "Application not ready, waiting ${retry_delay}s before retry..."
                sleep "$retry_delay"
            fi
        fi
    done
    
    log_error "Application failed to become ready after $max_retries attempts"
    return 1
}

# Function to run Laravel health check command
run_laravel_health_check() {
    log_info "Running Laravel health check command..."
    
    if command -v php >/dev/null 2>&1; then
        if php artisan deployment:health-check --format=json 2>/dev/null; then
            log_success "Laravel health check passed"
            return 0
        else
            log_error "Laravel health check failed"
            return 1
        fi
    else
        log_warning "PHP not available, skipping Laravel health check"
        return 0
    fi
}

# Function to send deployment notification
send_deployment_notification() {
    local status="$1"
    local message="$2"
    
    # This can be extended to send notifications to Slack, email, etc.
    log_info "Deployment status: $status - $message"
    
    # Example: Send to webhook if configured
    if [ -n "$DEPLOYMENT_WEBHOOK_URL" ]; then
        curl -X POST "$DEPLOYMENT_WEBHOOK_URL" \
            -H "Content-Type: application/json" \
            -d "{\"status\":\"$status\",\"message\":\"$message\",\"timestamp\":\"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"}" \
            >/dev/null 2>&1 || true
    fi
}

# Main execution
main() {
    log_info "🚀 Starting deployment health verification"
    log_info "Health check URL: $HEALTH_CHECK_URL"
    log_info "Max retries: $MAX_RETRIES"
    log_info "Retry delay: ${RETRY_DELAY}s"
    log_info "Timeout: ${TIMEOUT}s"
    echo
    
    # Wait for application to be ready
    if ! wait_for_app_ready "$HEALTH_CHECK_URL" "$MAX_RETRIES" "$RETRY_DELAY" "$TIMEOUT"; then
        send_deployment_notification "failed" "Application failed to become ready"
        exit 1
    fi
    
    echo
    
    # Run Laravel health check command if available
    run_laravel_health_check
    
    echo
    
    # Perform comprehensive health check
    if perform_health_check "$HEALTH_CHECK_URL" "$TIMEOUT"; then
        send_deployment_notification "success" "Deployment health verification passed"
        log_success "🎉 Deployment health verification completed successfully!"
        exit 0
    else
        send_deployment_notification "failed" "Deployment health verification failed"
        log_error "💥 Deployment health verification failed!"
        exit 1
    fi
}

# Check dependencies
if ! command -v curl >/dev/null 2>&1; then
    log_error "curl is required but not installed"
    exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
    log_error "jq is required but not installed"
    exit 1
fi

# Run main function
main "$@"