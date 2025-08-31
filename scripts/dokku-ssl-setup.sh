#!/bin/bash

# Dokku SSL Certificate Setup Script
# Manages SSL certificates for Dokku apps using Let's Encrypt

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

info() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] INFO: $1${NC}"
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
    exit 1
}

# Usage function
usage() {
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Commands:"
    echo "  setup <app_name>             Setup SSL for a specific app"
    echo "  setup-all                    Setup SSL for all apps"
    echo "  renew <app_name>             Renew SSL certificate for a specific app"
    echo "  renew-all                    Renew SSL certificates for all apps"
    echo "  status <app_name>            Check SSL status for a specific app"
    echo "  enable-cron                  Enable automatic SSL renewal cron job"
    echo "  disable-cron                 Disable automatic SSL renewal cron job"
    echo ""
    echo "Options:"
    echo "  --email <email>              Email for Let's Encrypt registration"
    echo "  --force                      Force SSL setup even if already configured"
    echo "  --help                       Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 setup restant-main --email admin@susankshakya.com.np"
    echo "  $0 setup-all --email admin@susankshakya.com.np"
    echo "  $0 status restant-main"
    echo "  $0 enable-cron"
}

# Check if Let's Encrypt plugin is installed
check_letsencrypt_plugin() {
    if ! dokku plugin:list | grep -q letsencrypt; then
        error "Let's Encrypt plugin is not installed. Please install it first with: dokku plugin:install https://github.com/dokku/dokku-letsencrypt.git"
    fi
    log "Let's Encrypt plugin verified"
}

# Setup SSL for a specific app
setup_ssl_for_app() {
    local app_name=$1
    local email=$2
    local force=$3
    
    log "Setting up SSL for app: $app_name"
    
    # Validate app exists
    if ! dokku apps:exists $app_name 2>/dev/null; then
        error "App $app_name does not exist"
    fi
    
    # Check if SSL is already configured
    if dokku letsencrypt:list | grep -q $app_name && [[ "$force" != "true" ]]; then
        warn "SSL already configured for $app_name. Use --force to reconfigure."
        return 0
    fi
    
    # Set email for Let's Encrypt if provided
    if [[ -n "$email" ]]; then
        info "Setting Let's Encrypt email: $email"
        dokku config:set --global DOKKU_LETSENCRYPT_EMAIL=$email
    fi
    
    # Get app domains
    local domains=$(dokku domains:report $app_name --domains-app-vhosts)
    if [[ -z "$domains" ]]; then
        error "No domains configured for app $app_name. Please configure domains first."
    fi
    
    info "Domains for $app_name: $domains"
    
    # Enable SSL
    log "Enabling SSL certificate for $app_name"
    if dokku letsencrypt:enable $app_name; then
        log "SSL certificate successfully enabled for $app_name"
    else
        error "Failed to enable SSL certificate for $app_name"
    fi
    
    # Verify SSL status
    info "SSL certificate status for $app_name:"
    dokku letsencrypt:list | grep $app_name || warn "SSL status not found in list"
}

# Setup SSL for all apps
setup_ssl_for_all_apps() {
    local email=$1
    local force=$2
    
    log "Setting up SSL for all Dokku apps"
    
    # Get list of all apps
    local apps=$(dokku apps:list | tail -n +2)
    
    if [[ -z "$apps" ]]; then
        warn "No apps found"
        return 0
    fi
    
    # Setup SSL for each app
    for app in $apps; do
        log "Processing app: $app"
        setup_ssl_for_app "$app" "$email" "$force"
        echo ""
    done
    
    log "SSL setup completed for all apps"
}

# Renew SSL certificate for a specific app
renew_ssl_for_app() {
    local app_name=$1
    
    log "Renewing SSL certificate for app: $app_name"
    
    # Validate app exists
    if ! dokku apps:exists $app_name 2>/dev/null; then
        error "App $app_name does not exist"
    fi
    
    # Check if SSL is configured
    if ! dokku letsencrypt:list | grep -q $app_name; then
        error "SSL is not configured for app $app_name. Please setup SSL first."
    fi
    
    # Renew certificate
    if dokku letsencrypt:renew $app_name; then
        log "SSL certificate successfully renewed for $app_name"
    else
        error "Failed to renew SSL certificate for $app_name"
    fi
}

# Renew SSL certificates for all apps
renew_ssl_for_all_apps() {
    log "Renewing SSL certificates for all apps"
    
    # Get list of apps with SSL enabled
    local ssl_apps=$(dokku letsencrypt:list | tail -n +2 | awk '{print $1}')
    
    if [[ -z "$ssl_apps" ]]; then
        warn "No apps with SSL certificates found"
        return 0
    fi
    
    # Renew SSL for each app
    for app in $ssl_apps; do
        log "Renewing SSL for app: $app"
        renew_ssl_for_app "$app"
        echo ""
    done
    
    log "SSL renewal completed for all apps"
}

# Check SSL status for a specific app
check_ssl_status() {
    local app_name=$1
    
    log "Checking SSL status for app: $app_name"
    
    # Validate app exists
    if ! dokku apps:exists $app_name 2>/dev/null; then
        error "App $app_name does not exist"
    fi
    
    # Show SSL status
    info "SSL certificate information for $app_name:"
    
    if dokku letsencrypt:list | grep -q $app_name; then
        dokku letsencrypt:list | grep $app_name
        
        # Show certificate details
        info "Certificate details:"
        dokku letsencrypt:show $app_name 2>/dev/null || warn "Could not retrieve certificate details"
    else
        warn "No SSL certificate found for $app_name"
    fi
    
    # Check domains
    info "Configured domains:"
    dokku domains:report $app_name --domains-app-vhosts
}

# Enable automatic SSL renewal cron job
enable_ssl_cron() {
    log "Enabling automatic SSL renewal cron job"
    
    if dokku letsencrypt:cron-job --add; then
        log "SSL renewal cron job enabled successfully"
        info "Cron job will run daily to check and renew certificates"
    else
        error "Failed to enable SSL renewal cron job"
    fi
    
    # Show current cron jobs
    info "Current Let's Encrypt cron jobs:"
    crontab -l | grep letsencrypt || warn "No Let's Encrypt cron jobs found"
}

# Disable automatic SSL renewal cron job
disable_ssl_cron() {
    log "Disabling automatic SSL renewal cron job"
    
    if dokku letsencrypt:cron-job --remove; then
        log "SSL renewal cron job disabled successfully"
    else
        error "Failed to disable SSL renewal cron job"
    fi
}

# Main function
main() {
    local command=""
    local app_name=""
    local email=""
    local force="false"
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --help)
                usage
                exit 0
                ;;
            --email)
                email="$2"
                shift 2
                ;;
            --force)
                force="true"
                shift
                ;;
            setup|setup-all|renew|renew-all|status|enable-cron|disable-cron)
                command="$1"
                shift
                ;;
            -*)
                error "Unknown option: $1"
                ;;
            *)
                if [[ -z "$app_name" ]]; then
                    app_name="$1"
                fi
                shift
                ;;
        esac
    done
    
    # Validate command
    if [[ -z "$command" ]]; then
        error "Command is required"
    fi
    
    check_letsencrypt_plugin
    
    # Execute command
    case $command in
        setup)
            if [[ -z "$app_name" ]]; then
                error "App name is required for setup command"
            fi
            setup_ssl_for_app "$app_name" "$email" "$force"
            ;;
        setup-all)
            setup_ssl_for_all_apps "$email" "$force"
            ;;
        renew)
            if [[ -z "$app_name" ]]; then
                error "App name is required for renew command"
            fi
            renew_ssl_for_app "$app_name"
            ;;
        renew-all)
            renew_ssl_for_all_apps
            ;;
        status)
            if [[ -z "$app_name" ]]; then
                error "App name is required for status command"
            fi
            check_ssl_status "$app_name"
            ;;
        enable-cron)
            enable_ssl_cron
            ;;
        disable-cron)
            disable_ssl_cron
            ;;
        *)
            error "Unknown command: $command"
            ;;
    esac
    
    log "SSL management operation completed successfully!"
}

# Run main function if script is executed directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi