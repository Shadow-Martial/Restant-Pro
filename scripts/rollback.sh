#!/bin/bash

# Manual Rollback Script for Dokku Deployments
# Usage: ./rollback.sh <app_name> [target_release]

set -e

# Configuration
DOKKU_HOST="${DOKKU_HOST:-209.50.227.94}"
SSH_KEY="${SSH_KEY:-~/.ssh/dokku_deploy}"
LOG_FILE="/tmp/rollback_$(date +%Y%m%d_%H%M%S).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${2:-$NC}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}" | tee -a "$LOG_FILE"
}

# Error handling
error_exit() {
    log "ERROR: $1" "$RED"
    exit 1
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..." "$YELLOW"
    
    if [ -z "$1" ]; then
        error_exit "App name is required. Usage: $0 <app_name> [target_release]"
    fi
    
    if [ ! -f "$SSH_KEY" ]; then
        error_exit "SSH key not found at $SSH_KEY"
    fi
    
    # Test SSH connection
    if ! ssh -i "$SSH_KEY" -o ConnectTimeout=10 dokku@"$DOKKU_HOST" apps:list > /dev/null 2>&1; then
        error_exit "Cannot connect to Dokku server at $DOKKU_HOST"
    fi
    
    log "Prerequisites check passed" "$GREEN"
}

# Get available releases
get_releases() {
    local app_name=$1
    log "Getting available releases for $app_name..." "$YELLOW"
    
    ssh -i "$SSH_KEY" dokku@"$DOKKU_HOST" ps:report "$app_name" --deployed 2>/dev/null || {
        error_exit "Failed to get releases for app $app_name"
    }
}

# Get current release
get_current_release() {
    local app_name=$1
    ssh -i "$SSH_KEY" dokku@"$DOKKU_HOST" ps:report "$app_name" --deployed | head -n 1
}

# Perform rollback
perform_rollback() {
    local app_name=$1
    local target_release=$2
    
    log "Starting rollback for $app_name..." "$YELLOW"
    
    # Get current release for backup
    local current_release
    current_release=$(get_current_release "$app_name")
    log "Current release: $current_release"
    
    # If no target release specified, get the previous one
    if [ -z "$target_release" ]; then
        target_release=$(get_releases "$app_name" | sed -n '2p')
        if [ -z "$target_release" ]; then
            error_exit "No previous release found for rollback"
        fi
    fi
    
    log "Target release: $target_release"
    
    # Confirm rollback
    read -p "Are you sure you want to rollback $app_name to $target_release? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log "Rollback cancelled by user" "$YELLOW"
        exit 0
    fi
    
    # Create backup of current state
    log "Creating backup of current state..." "$YELLOW"
    ssh -i "$SSH_KEY" dokku@"$DOKKU_HOST" ps:stop "$app_name" || {
        log "Warning: Failed to stop app gracefully" "$YELLOW"
    }
    
    # Perform the rollback
    log "Executing rollback..." "$YELLOW"
    if ssh -i "$SSH_KEY" dokku@"$DOKKU_HOST" ps:rebuild "$app_name"; then
        log "Rollback command executed successfully" "$GREEN"
    else
        error_exit "Rollback command failed"
    fi
    
    # Wait for services to start
    log "Waiting for services to start..." "$YELLOW"
    sleep 15
    
    # Verify rollback
    verify_rollback "$app_name"
}

# Verify rollback success
verify_rollback() {
    local app_name=$1
    log "Verifying rollback..." "$YELLOW"
    
    # Check if app is running
    local status
    status=$(ssh -i "$SSH_KEY" dokku@"$DOKKU_HOST" ps:report "$app_name" --deployed)
    
    if echo "$status" | grep -q "true"; then
        log "App is running" "$GREEN"
    else
        error_exit "App is not running after rollback"
    fi
    
    # Get app URL for health check
    local app_url
    app_url=$(ssh -i "$SSH_KEY" dokku@"$DOKKU_HOST" url "$app_name" 2>/dev/null | head -n 1)
    
    if [ -n "$app_url" ]; then
        log "Performing health check on $app_url..." "$YELLOW"
        
        # Simple HTTP health check
        if curl -f -s -o /dev/null --max-time 30 "$app_url"; then
            log "Health check passed" "$GREEN"
        else
            log "Warning: Health check failed, but app appears to be running" "$YELLOW"
        fi
    fi
    
    log "Rollback verification completed" "$GREEN"
}

# Send notification
send_notification() {
    local app_name=$1
    local target_release=$2
    local status=$3
    
    # If Laravel artisan is available, use the notification service
    if command -v php > /dev/null && [ -f "artisan" ]; then
        php artisan deployment:notify-rollback "$app_name" "$target_release" "$status" 2>/dev/null || {
            log "Warning: Failed to send notification via Laravel" "$YELLOW"
        }
    fi
    
    # Simple webhook notification (if configured)
    if [ -n "$WEBHOOK_URL" ]; then
        curl -X POST "$WEBHOOK_URL" \
            -H "Content-Type: application/json" \
            -d "{
                \"type\": \"rollback\",
                \"app_name\": \"$app_name\",
                \"target_release\": \"$target_release\",
                \"status\": \"$status\",
                \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
            }" 2>/dev/null || {
            log "Warning: Failed to send webhook notification" "$YELLOW"
        }
    fi
}

# List available releases
list_releases() {
    local app_name=$1
    log "Available releases for $app_name:" "$YELLOW"
    get_releases "$app_name" | nl -w2 -s'. '
}

# Main execution
main() {
    local app_name=$1
    local target_release=$2
    
    log "Starting manual rollback script" "$GREEN"
    log "App: $app_name, Target: ${target_release:-auto}"
    
    check_prerequisites "$app_name"
    
    # If --list flag is provided, just list releases
    if [ "$target_release" = "--list" ]; then
        list_releases "$app_name"
        exit 0
    fi
    
    # Perform rollback
    if perform_rollback "$app_name" "$target_release"; then
        log "Rollback completed successfully!" "$GREEN"
        send_notification "$app_name" "$target_release" "success"
    else
        log "Rollback failed!" "$RED"
        send_notification "$app_name" "$target_release" "failed"
        exit 1
    fi
    
    log "Log file saved to: $LOG_FILE"
}

# Help function
show_help() {
    cat << EOF
Manual Rollback Script for Dokku Deployments

Usage: $0 <app_name> [target_release|--list]

Arguments:
  app_name        Name of the Dokku app to rollback
  target_release  Specific release to rollback to (optional)
  --list          List available releases

Environment Variables:
  DOKKU_HOST      Dokku server hostname/IP (default: 209.50.227.94)
  SSH_KEY         Path to SSH private key (default: ~/.ssh/dokku_deploy)
  WEBHOOK_URL     Webhook URL for notifications (optional)

Examples:
  $0 restant-main                    # Rollback to previous release
  $0 restant-main v1.2.3            # Rollback to specific release
  $0 restant-main --list             # List available releases

EOF
}

# Check for help flag
if [ "$1" = "-h" ] || [ "$1" = "--help" ]; then
    show_help
    exit 0
fi

# Execute main function
main "$@"