#!/bin/bash

# Dokku Setup Script for Laravel Multi-tenant SaaS Platform
# This script sets up Dokku apps with proper configuration for automated deployment

set -e

# Configuration
DOKKU_HOST="209.50.227.94"
BASE_DOMAIN="susankshakya.com.np"
MYSQL_SERVICE="mysql-restant"
REDIS_SERVICE="redis-restant"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
    exit 1
}

# Check if running on Dokku server
check_dokku_server() {
    if ! command -v dokku &> /dev/null; then
        error "Dokku is not installed on this server. Please install Dokku first."
    fi
    log "Dokku installation verified"
}

# Create MySQL service if it doesn't exist
setup_mysql_service() {
    log "Setting up MySQL service: $MYSQL_SERVICE"
    
    if dokku mysql:exists $MYSQL_SERVICE 2>/dev/null; then
        warn "MySQL service $MYSQL_SERVICE already exists"
    else
        log "Creating MySQL service: $MYSQL_SERVICE"
        dokku mysql:create $MYSQL_SERVICE
        log "MySQL service created successfully"
    fi
}

# Create Redis service if it doesn't exist
setup_redis_service() {
    log "Setting up Redis service: $REDIS_SERVICE"
    
    if dokku redis:exists $REDIS_SERVICE 2>/dev/null; then
        warn "Redis service $REDIS_SERVICE already exists"
    else
        log "Creating Redis service: $REDIS_SERVICE"
        dokku redis:create $REDIS_SERVICE
        log "Redis service created successfully"
    fi
}

# Create Dokku app with configuration
create_dokku_app() {
    local app_name=$1
    local subdomain=$2
    local environment=$3
    
    log "Creating Dokku app: $app_name"
    
    # Create app if it doesn't exist
    if dokku apps:exists $app_name 2>/dev/null; then
        warn "App $app_name already exists"
    else
        dokku apps:create $app_name
        log "App $app_name created successfully"
    fi
    
    # Configure domain
    local full_domain="restant.$subdomain.$BASE_DOMAIN"
    log "Configuring domain: $full_domain"
    dokku domains:clear $app_name
    dokku domains:add $app_name $full_domain
    
    # Link services
    log "Linking MySQL service to $app_name"
    dokku mysql:link $MYSQL_SERVICE $app_name
    
    log "Linking Redis service to $app_name"
    dokku redis:link $REDIS_SERVICE $app_name
    
    # Configure SSL
    log "Enabling SSL for $app_name"
    dokku letsencrypt:enable $app_name
    dokku letsencrypt:cron-job --add
    
    # Set basic Laravel configuration
    log "Setting Laravel environment variables for $app_name"
    dokku config:set $app_name \
        APP_ENV=$environment \
        APP_DEBUG=false \
        APP_KEY=$(openssl rand -base64 32) \
        LOG_CHANNEL=stack \
        CACHE_DRIVER=redis \
        SESSION_DRIVER=redis \
        QUEUE_CONNECTION=redis
    
    log "App $app_name configured successfully"
}

# Main setup function
main() {
    log "Starting Dokku setup for Laravel Multi-tenant SaaS Platform"
    
    check_dokku_server
    setup_mysql_service
    setup_redis_service
    
    # Create production app
    create_dokku_app "restant-main" "main" "production"
    
    # Create staging app
    create_dokku_app "restant-staging" "staging" "staging"
    
    log "Dokku setup completed successfully!"
    log "Production app: restant.main.$BASE_DOMAIN"
    log "Staging app: restant.staging.$BASE_DOMAIN"
}

# Run main function if script is executed directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi