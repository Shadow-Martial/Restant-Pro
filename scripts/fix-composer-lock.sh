#!/bin/bash

# Script to fix composer.lock file when it's out of sync with composer.json
# This script should be run locally to regenerate the lock file

echo "🔧 Fixing Composer Lock File"
echo "=============================="

# Check if composer.json exists
if [ ! -f "composer.json" ]; then
    echo "❌ Error: composer.json not found in current directory"
    exit 1
fi

# Backup existing composer.lock if it exists
if [ -f "composer.lock" ]; then
    echo "📦 Backing up existing composer.lock..."
    cp composer.lock composer.lock.backup
    echo "✅ Backup created: composer.lock.backup"
fi

# Remove the outdated lock file
echo "🗑️  Removing outdated composer.lock..."
rm -f composer.lock

# Install dependencies and generate new lock file
echo "📥 Installing dependencies and generating new lock file..."
composer install --no-interaction

# Verify the installation
echo "🔍 Verifying installation..."
if composer validate --no-check-publish; then
    echo "✅ Composer files are valid!"
else
    echo "❌ Composer validation failed"
    exit 1
fi

# Show what packages were installed
echo ""
echo "📋 Installed packages:"
echo "======================"
composer show --direct

echo ""
echo "🎉 Composer lock file has been successfully regenerated!"
echo ""
echo "📝 Next steps:"
echo "1. Review the changes: git diff composer.lock"
echo "2. Commit the updated lock file: git add composer.lock && git commit -m 'Update composer.lock'"
echo "3. Push to trigger CI/CD: git push"
echo ""
echo "🔄 If you need to restore the backup: mv composer.lock.backup composer.lock"