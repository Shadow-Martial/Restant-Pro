#!/bin/bash

# Dokku App Configuration Script
# Configures individual Dokku apps with environment-specific settings

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
    echo "Usage: $0 <app_name> <environment> [options]"
    echo ""
    echo "Arguments:"
    echo "  app_name     Name of the Dokku app (e.g., restant-main)"
    echo "  environment  Environment type (production, staging, development)"
    echo ""
    echo "Options:"
    echo "  --sentry-dsn <dsn>           Set Sentry DSN for error tracking"
    echo "  --flagsmith-key <key>        Set Flagsmith environment key"
    echo "  --flagsmith-url <url>        Set Flagsmith API URL"
    echo "  --grafana-key <key>          Set Grafana Cloud API key"
    echo "  --grafana-instance <id>      Set Grafana Cloud instance ID"
    echo "  --app-url <url>              Set application URL"
    echo "  --help                       Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 restant-main production --app-url https://restant.main.susankshakya.com.np"
    echo "  $0 restant-staging staging --sentry-dsn https://xxx@sentry.io/xxx"
}

# Validate app exists
validate_app() {
    local app_name=$1
    
    if ! dokku apps:exists $app_name 2>/dev/null; then
        error "App $app_name does not exist. Please create it first using dokku-setup.sh"
    fi
    
    log "App $app_name validated"
}

# Configure Laravel-specific settings
configure_laravel_settings() {
    local app_name=$1
    local environment=$2
    
    log "Configuring Laravel settings for $app_name ($environment)"
    
    # Base Laravel configuration
    local config_vars=(
        "APP_ENV=$environment"
        "LOG_CHANNEL=stack"
        "LOG_DEPRECATIONS_CHANNEL=null"
        "LOG_LEVEL=debug"
        "BROADCAST_DRIVER=log"
        "CACHE_DRIVER=redis"
        "FILESYSTEM_DISK=local"
        "QUEUE_CONNECTION=redis"
        "SESSION_DRIVER=redis"
        "SESSION_LIFETIME=120"
    )
    
    # Environment-specific settings
    if [[ "$environment" == "production" ]]; then
        config_vars+=(
            "APP_DEBUG=false"
            "LOG_LEVEL=error"
        )
    else
        config_vars+=(
            "APP_DEBUG=true"
            "LOG_LEVEL=debug"
        )
    fi
    
    # Apply configuration
    for config in "${config_vars[@]}"; do
        dokku config:set $app_name $config
    done
    
    log "Laravel settings configured for $app_name"
}

# Configure monitoring services
configure_monitoring() {
    local app_name=$1
    local sentry_dsn=$2
    local flagsmith_key=$3
    local flagsmith_url=$4
    local grafana_key=$5
    local grafana_instance=$6
    
    log "Configuring monitoring services for $app_name"
    
    # Sentry configuration
    if [[ -n "$sentry_dsn" ]]; then
        info "Configuring Sentry integration"
        dokku config:set $app_name \
            SENTRY_LARAVEL_DSN="$sentry_dsn" \
            SENTRY_TRACES_SAMPLE_RATE=0.1 \
            SENTRY_PROFILES_SAMPLE_RATE=0.1
    fi
    
    # Flagsmith configuration
    if [[ -n "$flagsmith_key" ]]; then
        info "Configuring Flagsmith integration"
        dokku config:set $app_name FLAGSMITH_ENVIRONMENT_KEY="$flagsmith_key"
        
        if [[ -n "$flagsmith_url" ]]; then
            dokku config:set $app_name FLAGSMITH_API_URL="$flagsmith_url"
        fi
    fi
    
    # Grafana Cloud configuration
    if [[ -n "$grafana_key" ]]; then
        info "Configuring Grafana Cloud integration"
        dokku config:set $app_name GRAFANA_CLOUD_API_KEY="$grafana_key"
        
        if [[ -n "$grafana_instance" ]]; then
            dokku config:set $app_name GRAFANA_CLOUD_INSTANCE_ID="$grafana_instance"
        fi
    fi
    
    log "Monitoring services configured for $app_name"
}

# Configure SSL and domains
configure_ssl_domains() {
    local app_name=$1
    local app_url=$2
    
    log "Configuring SSL and domains for $app_name"
    
    if [[ -n "$app_url" ]]; then
        # Extract domain from URL
        local domain=$(echo $app_url | sed 's|https\?://||' | sed 's|/.*||')
        
        info "Setting domain: $domain"
        dokku domains:clear $app_name
        dokku domains:add $app_name $domain
        
        # Configure SSL
        info "Enabling SSL for $domain"
        dokku letsencrypt:enable $app_name
        
        # Set APP_URL
        dokku config:set $app_name APP_URL="$app_url"
    fi
    
    log "SSL and domains configured for $app_name"
}

# Configure build and deployment settings
configure_deployment() {
    local app_name=$1
    
    log "Configuring deployment settings for $app_name"
    
    # Set buildpack for Laravel
    dokku buildpacks:set $app_name https://github.com/heroku/heroku-buildpack-php
    
    # Configure PHP settings
    dokku config:set $app_name \
        PHP_VERSION=8.1 \
        COMPOSER_MEMORY_LIMIT=-1
    
    # Set deployment checks
    dokku checks:disable $app_name web
    dokku checks:enable $app_name
    
    log "Deployment settings configured for $app_name"
}

# Main configuration function
main() {
    local app_name=""
    local environment=""
    local sentry_dsn=""
    local flagsmith_key=""
    local flagsmith_url=""
    local grafana_key=""
    local grafana_instance=""
    local app_url=""
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --help)
                usage
                exit 0
                ;;
            --sentry-dsn)
                sentry_dsn="$2"
                shift 2
                ;;
            --flagsmith-key)
                flagsmith_key="$2"
                shift 2
                ;;
            --flagsmith-url)
                flagsmith_url="$2"
                shift 2
                ;;
            --grafana-key)
                grafana_key="$2"
                shift 2
                ;;
            --grafana-instance)
                grafana_instance="$2"
                shift 2
                ;;
            --app-url)
                app_url="$2"
                shift 2
                ;;
            -*)
                error "Unknown option: $1"
                ;;
            *)
                if [[ -z "$app_name" ]]; then
                    app_name="$1"
                elif [[ -z "$environment" ]]; then
                    environment="$1"
                else
                    error "Too many arguments"
                fi
                shift
                ;;
        esac
    done
    
    # Validate required arguments
    if [[ -z "$app_name" || -z "$environment" ]]; then
        error "App name and environment are required"
    fi
    
    log "Starting configuration for app: $app_name (environment: $environment)"
    
    validate_app "$app_name"
    configure_laravel_settings "$app_name" "$environment"
    configure_monitoring "$app_name" "$sentry_dsn" "$flagsmith_key" "$flagsmith_url" "$grafana_key" "$grafana_instance"
    configure_ssl_domains "$app_name" "$app_url"
    configure_deployment "$app_name"
    
    log "Configuration completed successfully for $app_name!"
    
    # Show current configuration
    info "Current configuration for $app_name:"
    dokku config $app_name
}

# Run main function if script is executed directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi