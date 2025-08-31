# Fix Composer Lock File Guide

## 🚨 Problem
The GitHub Actions workflow is failing with exit code 4 because the `composer.lock` file is outdated and doesn't match the updated `composer.json` file.

**Error Message:**
```
Warning: The lock file is not up to date with the latest changes in composer.json.
- Required package "flagsmith/flagsmith-php-client" is not present in the lock file.
- Required package "sentry/sentry-laravel" is not present in the lock file.
```

## 🔧 Solution Options

### Option 1: Automatic Fix (Recommended)
The GitHub Actions workflows have been updated to automatically handle this issue. Simply push your changes and the workflow will:

1. Detect the outdated lock file
2. Run `composer update` to regenerate it
3. Continue with the deployment/testing

### Option 2: Manual Fix (Local)
If you want to fix this locally before pushing:

#### Method A: Using the Fix Script
```bash
# Make the script executable (Unix/Linux/Mac)
chmod +x scripts/fix-composer-lock.sh

# Run the fix script
./scripts/fix-composer-lock.sh

# Commit the updated lock file
git add composer.lock
git commit -m "Update composer.lock to match composer.json"
git push
```

#### Method B: Manual Commands
```bash
# Remove the outdated lock file
rm composer.lock

# Regenerate the lock file
composer install

# Or if you want to update to latest compatible versions
composer update

# Commit the changes
git add composer.lock
git commit -m "Regenerate composer.lock with updated dependencies"
git push
```

## 🔍 What Changed

### Added Dependencies
- `sentry/sentry-laravel: ^4.0` - Error tracking and monitoring
- `flagsmith/flagsmith-php-client: ^2.0` - Feature flag management

### Updated Dependencies
- `nunomaduro/collision: ^7.0` (was `^6.1`)
- `phpunit/phpunit: ^10.1` (was `^9.5.10`)
- `laravel/sail: ^1.18` (was `*`)

## 🚀 Verification

After fixing the lock file, verify everything works:

### 1. Check Composer Status
```bash
# Validate composer files
composer validate

# Check installed packages
composer show

# Verify new packages are installed
composer show sentry/sentry-laravel
composer show flagsmith/flagsmith-php-client
```

### 2. Test Locally
```bash
# Install dependencies
composer install

# Run tests
php artisan test

# Or run PHPUnit directly
vendor/bin/phpunit
```

### 3. Test GitHub Actions
```bash
# Push to trigger workflows
git push origin main        # For production deployment
git push origin staging     # For staging deployment
```

## 🛠️ Troubleshooting

### If Composer Update Fails
```bash
# Clear composer cache
composer clear-cache

# Update with verbose output
composer update --verbose

# If memory issues occur
COMPOSER_MEMORY_LIMIT=-1 composer update
```

### If Dependencies Conflict
```bash
# Check what's causing conflicts
composer why-not sentry/sentry-laravel
composer why-not flagsmith/flagsmith-php-client

# Update specific packages
composer update sentry/sentry-laravel flagsmith/flagsmith-php-client
```

### If Tests Still Fail
1. Check that `.env.testing` exists and has correct database settings
2. Verify `phpunit.xml` is properly configured
3. Ensure basic test files exist in `tests/` directory

## 📋 Files Modified

### Updated Files:
- `composer.json` - Added monitoring dependencies and updated versions
- `.github/workflows/test.yml` - Enhanced to handle lock file issues
- `.github/workflows/deploy.yml` - Enhanced to handle lock file issues
- `phpunit.xml` - Updated to PHPUnit 10 format
- `.env.testing` - Updated to use MySQL instead of SQLite

### New Files:
- `.env.example` - Environment configuration template
- `scripts/fix-composer-lock.sh` - Automated fix script
- `tests/Feature/BasicTest.php` - Basic feature tests
- `tests/Unit/BasicUnitTest.php` - Basic unit tests

## 🎯 Expected Results

After fixing the composer.lock file:

✅ **GitHub Actions Tests**: Should pass without exit code 4  
✅ **Dependency Installation**: All packages install correctly  
✅ **Monitoring Integration**: Sentry and Flagsmith packages available  
✅ **Deployment**: Automated deployment works smoothly  

## 🔄 Prevention

To prevent this issue in the future:

1. **Always commit composer.lock**: Never add it to `.gitignore`
2. **Use composer commands**: Use `composer require` instead of manually editing `composer.json`
3. **Update lock file**: Run `composer update` after manually editing `composer.json`
4. **Test locally**: Always test locally before pushing changes

## 📞 Support

If you continue to experience issues:

1. Check the GitHub Actions logs for detailed error messages
2. Verify all required PHP extensions are installed
3. Ensure PHP 8.1+ is being used
4. Check that MySQL service is properly configured in the workflow

The automated fixes in the GitHub Actions workflows should resolve this issue automatically on the next push! 🚀