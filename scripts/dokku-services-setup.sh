#!/bin/bash

# Dokku Services Setup Script
# Manages database and Redis services for Dokku apps

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
    echo "  create-mysql <service_name>          Create MySQL service"
    echo "  create-redis <service_name>          Create Redis service"
    echo "  link-mysql <service_name> <app>      Link MySQL service to app"
    echo "  link-redis <service_name> <app>      Link Redis service to app"
    echo "  unlink-mysql <service_name> <app>    Unlink MySQL service from app"
    echo "  unlink-redis <service_name> <app>    Unlink Redis service from app"
    echo "  backup-mysql <service_name>          Backup MySQL service"
    echo "  restore-mysql <service_name> <file>  Restore MySQL service from backup"
    echo "  status-mysql <service_name>          Show MySQL service status"
    echo "  status-redis <service_name>          Show Redis service status"
    echo "  list-services                        List all services"
    echo "  setup-all                            Setup all required services"
    echo ""
    echo "Options:"
    echo "  --mysql-version <version>            MySQL version (default: 8.0)"
    echo "  --redis-version <version>            Redis version (default: 7.0)"
    echo "  --backup-dir <path>                  Backup directory (default: /var/backups/dokku)"
    echo "  --help                               Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 create-mysql mysql-restant"
    echo "  $0 create-redis redis-restant"
    echo "  $0 link-mysql mysql-restant restant-main"
    echo "  $0 setup-all"
}

# Check if required plugins are installed
check_plugins() {
    local missing_plugins=()
    
    if ! dokku plugin:list | grep -q mysql; then
        missing_plugins+=("mysql")
    fi
    
    if ! dokku plugin:list | grep -q redis; then
        missing_plugins+=("redis")
    fi
    
    if [[ ${#missing_plugins[@]} -gt 0 ]]; then
        error "Missing required plugins: ${missing_plugins[*]}. Please install them first."
    fi
    
    log "Required plugins verified"
}

# Create MySQL service
create_mysql_service() {
    local service_name=$1
    local mysql_version=$2
    
    log "Creating MySQL service: $service_name"
    
    if dokku mysql:exists $service_name 2>/dev/null; then
        warn "MySQL service $service_name already exists"
        return 0
    fi
    
    # Create MySQL service with specified version
    if [[ -n "$mysql_version" ]]; then
        info "Creating MySQL service with version: $mysql_version"
        dokku mysql:create $service_name --image-version $mysql_version
    else
        info "Creating MySQL service with default version"
        dokku mysql:create $service_name
    fi
    
    # Configure MySQL settings
    info "Configuring MySQL service settings"
    dokku mysql:set $service_name CHARACTER_SET_SERVER utf8mb4
    dokku mysql:set $service_name COLLATION_SERVER utf8mb4_unicode_ci
    
    log "MySQL service $service_name created successfully"
    
    # Show service info
    info "MySQL service information:"
    dokku mysql:info $service_name
}

# Create Redis service
create_redis_service() {
    local service_name=$1
    local redis_version=$2
    
    log "Creating Redis service: $service_name"
    
    if dokku redis:exists $service_name 2>/dev/null; then
        warn "Redis service $service_name already exists"
        return 0
    fi
    
    # Create Redis service with specified version
    if [[ -n "$redis_version" ]]; then
        info "Creating Redis service with version: $redis_version"
        dokku redis:create $service_name --image-version $redis_version
    else
        info "Creating Redis service with default version"
        dokku redis:create $service_name
    fi
    
    # Configure Redis settings
    info "Configuring Redis service settings"
    dokku redis:set $service_name REDIS_MAXMEMORY 256mb
    dokku redis:set $service_name REDIS_MAXMEMORY_POLICY allkeys-lru
    
    log "Redis service $service_name created successfully"
    
    # Show service info
    info "Redis service information:"
    dokku redis:info $service_name
}

# Link MySQL service to app
link_mysql_service() {
    local service_name=$1
    local app_name=$2
    
    log "Linking MySQL service $service_name to app $app_name"
    
    # Validate service exists
    if ! dokku mysql:exists $service_name 2>/dev/null; then
        error "MySQL service $service_name does not exist"
    fi
    
    # Validate app exists
    if ! dokku apps:exists $app_name 2>/dev/null; then
        error "App $app_name does not exist"
    fi
    
    # Link service to app
    dokku mysql:link $service_name $app_name
    
    log "MySQL service $service_name linked to app $app_name successfully"
    
    # Show linked services
    info "Linked services for $app_name:"
    dokku mysql:linked $service_name
}

# Link Redis service to app
link_redis_service() {
    local service_name=$1
    local app_name=$2
    
    log "Linking Redis service $service_name to app $app_name"
    
    # Validate service exists
    if ! dokku redis:exists $service_name 2>/dev/null; then
        error "Redis service $service_name does not exist"
    fi
    
    # Validate app exists
    if ! dokku apps:exists $app_name 2>/dev/null; then
        error "App $app_name does not exist"
    fi
    
    # Link service to app
    dokku redis:link $service_name $app_name
    
    log "Redis service $service_name linked to app $app_name successfully"
    
    # Show linked services
    info "Linked services for $app_name:"
    dokku redis:linked $service_name
}

# Unlink MySQL service from app
unlink_mysql_service() {
    local service_name=$1
    local app_name=$2
    
    log "Unlinking MySQL service $service_name from app $app_name"
    
    dokku mysql:unlink $service_name $app_name
    
    log "MySQL service $service_name unlinked from app $app_name successfully"
}

# Unlink Redis service from app
unlink_redis_service() {
    local service_name=$1
    local app_name=$2
    
    log "Unlinking Redis service $service_name from app $app_name"
    
    dokku redis:unlink $service_name $app_name
    
    log "Redis service $service_name unlinked from app $app_name successfully"
}

# Backup MySQL service
backup_mysql_service() {
    local service_name=$1
    local backup_dir=$2
    
    log "Creating backup for MySQL service: $service_name"
    
    # Validate service exists
    if ! dokku mysql:exists $service_name 2>/dev/null; then
        error "MySQL service $service_name does not exist"
    fi
    
    # Create backup directory if it doesn't exist
    mkdir -p "$backup_dir"
    
    # Create backup filename with timestamp
    local backup_file="$backup_dir/${service_name}_$(date +%Y%m%d_%H%M%S).sql"
    
    # Create backup
    info "Creating backup: $backup_file"
    dokku mysql:export $service_name > "$backup_file"
    
    # Compress backup
    gzip "$backup_file"
    backup_file="${backup_file}.gz"
    
    log "MySQL backup created successfully: $backup_file"
    
    # Show backup info
    info "Backup file size: $(du -h $backup_file | cut -f1)"
}

# Restore MySQL service from backup
restore_mysql_service() {
    local service_name=$1
    local backup_file=$2
    
    log "Restoring MySQL service $service_name from backup: $backup_file"
    
    # Validate service exists
    if ! dokku mysql:exists $service_name 2>/dev/null; then
        error "MySQL service $service_name does not exist"
    fi
    
    # Validate backup file exists
    if [[ ! -f "$backup_file" ]]; then
        error "Backup file $backup_file does not exist"
    fi
    
    # Restore from backup
    if [[ "$backup_file" == *.gz ]]; then
        info "Decompressing and restoring backup"
        gunzip -c "$backup_file" | dokku mysql:import $service_name
    else
        info "Restoring backup"
        dokku mysql:import $service_name < "$backup_file"
    fi
    
    log "MySQL service $service_name restored successfully from $backup_file"
}

# Show MySQL service status
show_mysql_status() {
    local service_name=$1
    
    log "MySQL service status for: $service_name"
    
    if ! dokku mysql:exists $service_name 2>/dev/null; then
        error "MySQL service $service_name does not exist"
    fi
    
    # Show service information
    dokku mysql:info $service_name
    
    # Show linked apps
    info "Linked apps:"
    dokku mysql:linked $service_name 2>/dev/null || info "No apps linked"
}

# Show Redis service status
show_redis_status() {
    local service_name=$1
    
    log "Redis service status for: $service_name"
    
    if ! dokku redis:exists $service_name 2>/dev/null; then
        error "Redis service $service_name does not exist"
    fi
    
    # Show service information
    dokku redis:info $service_name
    
    # Show linked apps
    info "Linked apps:"
    dokku redis:linked $service_name 2>/dev/null || info "No apps linked"
}

# List all services
list_all_services() {
    log "Listing all Dokku services"
    
    info "MySQL services:"
    dokku mysql:list 2>/dev/null || info "No MySQL services found"
    
    echo ""
    
    info "Redis services:"
    dokku redis:list 2>/dev/null || info "No Redis services found"
}

# Setup all required services
setup_all_services() {
    local mysql_version=$1
    local redis_version=$2
    
    log "Setting up all required services for Laravel multi-tenant platform"
    
    # Create shared MySQL service
    create_mysql_service "mysql-restant" "$mysql_version"
    
    # Create shared Redis service
    create_redis_service "redis-restant" "$redis_version"
    
    log "All services setup completed successfully!"
    
    # Show summary
    info "Service summary:"
    list_all_services
}

# Main function
main() {
    local command=""
    local service_name=""
    local app_name=""
    local backup_file=""
    local mysql_version=""
    local redis_version=""
    local backup_dir="/var/backups/dokku"
    
    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --help)
                usage
                exit 0
                ;;
            --mysql-version)
                mysql_version="$2"
                shift 2
                ;;
            --redis-version)
                redis_version="$2"
                shift 2
                ;;
            --backup-dir)
                backup_dir="$2"
                shift 2
                ;;
            create-mysql|create-redis|link-mysql|link-redis|unlink-mysql|unlink-redis|backup-mysql|restore-mysql|status-mysql|status-redis|list-services|setup-all)
                command="$1"
                shift
                ;;
            -*)
                error "Unknown option: $1"
                ;;
            *)
                if [[ -z "$service_name" ]]; then
                    service_name="$1"
                elif [[ -z "$app_name" && "$command" =~ (link|unlink) ]]; then
                    app_name="$1"
                elif [[ -z "$backup_file" && "$command" == "restore-mysql" ]]; then
                    backup_file="$1"
                fi
                shift
                ;;
        esac
    done
    
    # Validate command
    if [[ -z "$command" ]]; then
        error "Command is required"
    fi
    
    check_plugins
    
    # Execute command
    case $command in
        create-mysql)
            if [[ -z "$service_name" ]]; then
                error "Service name is required"
            fi
            create_mysql_service "$service_name" "$mysql_version"
            ;;
        create-redis)
            if [[ -z "$service_name" ]]; then
                error "Service name is required"
            fi
            create_redis_service "$service_name" "$redis_version"
            ;;
        link-mysql)
            if [[ -z "$service_name" || -z "$app_name" ]]; then
                error "Service name and app name are required"
            fi
            link_mysql_service "$service_name" "$app_name"
            ;;
        link-redis)
            if [[ -z "$service_name" || -z "$app_name" ]]; then
                error "Service name and app name are required"
            fi
            link_redis_service "$service_name" "$app_name"
            ;;
        unlink-mysql)
            if [[ -z "$service_name" || -z "$app_name" ]]; then
                error "Service name and app name are required"
            fi
            unlink_mysql_service "$service_name" "$app_name"
            ;;
        unlink-redis)
            if [[ -z "$service_name" || -z "$app_name" ]]; then
                error "Service name and app name are required"
            fi
            unlink_redis_service "$service_name" "$app_name"
            ;;
        backup-mysql)
            if [[ -z "$service_name" ]]; then
                error "Service name is required"
            fi
            backup_mysql_service "$service_name" "$backup_dir"
            ;;
        restore-mysql)
            if [[ -z "$service_name" || -z "$backup_file" ]]; then
                error "Service name and backup file are required"
            fi
            restore_mysql_service "$service_name" "$backup_file"
            ;;
        status-mysql)
            if [[ -z "$service_name" ]]; then
                error "Service name is required"
            fi
            show_mysql_status "$service_name"
            ;;
        status-redis)
            if [[ -z "$service_name" ]]; then
                error "Service name is required"
            fi
            show_redis_status "$service_name"
            ;;
        list-services)
            list_all_services
            ;;
        setup-all)
            setup_all_services "$mysql_version" "$redis_version"
            ;;
        *)
            error "Unknown command: $command"
            ;;
    esac
    
    log "Service management operation completed successfully!"
}

# Run main function if script is executed directly
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    main "$@"
fi