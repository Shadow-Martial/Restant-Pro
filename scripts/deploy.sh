#!/bin/bash

# Deployment script for Restant Pro
# This script handles post-deployment tasks

set -e

echo "🚀 Starting deployment tasks..."

# Check if we're in the right environment
if [ -z "$APP_ENV" ]; then
    echo "❌ APP_ENV not set"
    exit 1
fi

echo "📦 Environment: $APP_ENV"

# Run Laravel optimizations
echo "⚡ Running Laravel optimizations..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear any old caches
echo "🧹 Clearing old caches..."
php artisan cache:clear

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Restart queue workers
echo "🔄 Restarting queue workers..."
php artisan queue:restart

# Generate sitemap if in production
if [ "$APP_ENV" = "production" ]; then
    echo "🗺️ Generating sitemap..."
    php artisan sitemap:generate || echo "⚠️ Sitemap generation failed (non-critical)"
fi

echo "✅ Deployment tasks completed successfully!"