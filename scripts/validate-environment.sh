#!/bin/bash

# Environment Configuration Validation Script
# This script validates environment configuration before deployment

set -e

ENVIRONMENT=${1:-production}
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

echo "🔍 Validating environment configuration for: $ENVIRONMENT"
echo "=================================================="

# Change to project root
cd "$PROJECT_ROOT"

# Check if Laravel is available
if [ ! -f "artisan" ]; then
    echo "❌ Error: artisan command not found. Are you in the Laravel project root?"
    exit 1
fi

# Check if environment file exists
ENV_FILE=".env.$ENVIRONMENT"
if [ ! -f "$ENV_FILE" ]; then
    echo "❌ Error: Environment file $ENV_FILE not found"
    echo "💡 Create it from the template: cp ${ENV_FILE}.template $ENV_FILE"
    exit 1
fi

echo "✅ Environment file found: $ENV_FILE"

# Load environment file
set -a
source "$ENV_FILE"
set +a

echo "✅ Environment variables loaded"

# Run Laravel environment validation
echo ""
echo "🔍 Running Laravel environment validation..."
php artisan env:validate --environment="$ENVIRONMENT"

if [ $? -eq 0 ]; then
    echo "✅ Laravel environment validation passed"
else
    echo "❌ Laravel environment validation failed"
    exit 1
fi

# Test external services if not in testing environment
if [ "$ENVIRONMENT" != "testing" ]; then
    echo ""
    echo "🔍 Testing external service connections..."
    php artisan env:validate --environment="$ENVIRONMENT" --services
    
    if [ $? -eq 0 ]; then
        echo "✅ External service validation passed"
    else
        echo "⚠️  External service validation had issues (non-critical)"
    fi
fi

# Check required directories
echo ""
echo "🔍 Checking required directories..."

REQUIRED_DIRS=(
    "storage/app"
    "storage/framework/cache"
    "storage/framework/sessions"
    "storage/framework/views"
    "storage/logs"
    "bootstrap/cache"
)

for dir in "${REQUIRED_DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "❌ Missing directory: $dir"
        mkdir -p "$dir"
        echo "✅ Created directory: $dir"
    fi
done

# Check directory permissions
echo ""
echo "🔍 Checking directory permissions..."

WRITABLE_DIRS=(
    "storage"
    "bootstrap/cache"
)

for dir in "${WRITABLE_DIRS[@]}"; do
    if [ ! -w "$dir" ]; then
        echo "❌ Directory not writable: $dir"
        chmod -R 775 "$dir"
        echo "✅ Fixed permissions for: $dir"
    fi
done

# Validate composer dependencies
echo ""
echo "🔍 Validating composer dependencies..."

if [ ! -f "vendor/autoload.php" ]; then
    echo "❌ Composer dependencies not installed"
    echo "💡 Run: composer install --no-dev --optimize-autoloader"
    exit 1
fi

echo "✅ Composer dependencies are installed"

# Check for production optimizations
if [ "$ENVIRONMENT" = "production" ]; then
    echo ""
    echo "🔍 Checking production optimizations..."
    
    # Check if config is cached
    if [ ! -f "bootstrap/cache/config.php" ]; then
        echo "⚠️  Configuration not cached (run: php artisan config:cache)"
    else
        echo "✅ Configuration is cached"
    fi
    
    # Check if routes are cached
    if [ ! -f "bootstrap/cache/routes-v7.php" ]; then
        echo "⚠️  Routes not cached (run: php artisan route:cache)"
    else
        echo "✅ Routes are cached"
    fi
    
    # Check if views are cached
    VIEW_CACHE_DIR="storage/framework/views"
    if [ -z "$(ls -A $VIEW_CACHE_DIR 2>/dev/null)" ]; then
        echo "⚠️  Views not cached (run: php artisan view:cache)"
    else
        echo "✅ Views are cached"
    fi
fi

# Final summary
echo ""
echo "🎉 Environment validation completed successfully!"
echo "=================================================="
echo "Environment: $ENVIRONMENT"
echo "App Name: ${APP_NAME:-'Not Set'}"
echo "App URL: ${APP_URL:-'Not Set'}"
echo "Database: ${DB_DATABASE:-'Not Set'}"
echo "Cache Driver: ${CACHE_DRIVER:-'Not Set'}"
echo "=================================================="

exit 0