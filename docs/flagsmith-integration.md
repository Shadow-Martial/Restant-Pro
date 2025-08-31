# Flagsmith Integration Documentation

## Overview

This document describes the Flagsmith feature flag integration for the Laravel application. Flagsmith allows you to manage feature flags, A/B testing, and remote configuration without deploying code changes.

## Configuration

### Environment Variables

Add the following to your `.env` file:

```env
FLAGSMITH_ENABLED=true
FLAGSMITH_ENVIRONMENT_KEY=ser.your_environment_key_here
FLAGSMITH_API_URL=https://flagsmith.yourdomain.com/api/v1/
FLAGSMITH_CACHE_TTL=300
FLAGSMITH_FALLBACK_ENABLED=true
FLAGSMITH_LOG_FAILURES=true
```

### Default Flags

Configure default flag values in `config/flagsmith.php`:

```php
'default_flags' => [
    'new_ui_enabled' => false,
    'maintenance_mode' => false,
    'premium_features' => false,
],
```

## Usage Examples

### Helper Functions

```php
// Check if a feature is enabled
if (feature_enabled('new_ui_enabled')) {
    // Show new UI
}

// Get feature flag value with default
$maxUsers = feature_flag('max_users_per_tenant', 100);

// Check feature for specific user
if (user_feature_enabled('premium_features', $user)) {
    // Show premium features
}

// Check feature for current tenant
if (tenant_feature_enabled('advanced_analytics')) {
    // Show advanced analytics
}
```

### Facade Usage

```php
use App\Facades\Flagsmith;

// Get flag value
$value = Flagsmith::getFlag('feature_name', false);

// Check if enabled
$enabled = Flagsmith::isEnabled('feature_name');

// Get multiple flags
$flags = Flagsmith::getFlags([
    'feature_1' => false,
    'feature_2' => true,
    'max_items' => 10
]);
```

### Blade Directives

```blade
@feature('new_ui_enabled')
    <div class="new-ui-component">
        <!-- New UI content -->
    </div>
@endfeature

@userfeature('premium_features')
    <div class="premium-content">
        <!-- Premium features -->
    </div>
@enduserfeature

@tenantfeature('advanced_analytics')
    <div class="analytics-dashboard">
        <!-- Advanced analytics -->
    </div>
@endtenantfeature
```

### Middleware Usage

```php
// In routes/web.php
Route::group(['middleware' => 'feature:new_dashboard'], function () {
    Route::get('/dashboard/v2', [DashboardController::class, 'newDashboard']);
});

// With redirect
Route::get('/premium', [PremiumController::class, 'index'])
    ->middleware('feature:premium_features,dashboard');

// Maintenance mode
Route::group(['middleware' => 'maintenance.flag'], function () {
    // These routes will show maintenance page if flag is enabled
});
```

### Service Usage

```php
use App\Services\FlagsmithService;

class FeatureController extends Controller
{
    public function __construct(
        private FlagsmithService $flagsmith
    ) {}

    public function checkFeature(Request $request)
    {
        $userId = $request->user()->id;
        $isEnabled = $this->flagsmith->isEnabled('beta_feature', (string) $userId);
        
        return response()->json(['enabled' => $isEnabled]);
    }
}
```

## Fallback Mechanisms

The integration includes several fallback mechanisms:

1. **Cache Fallback**: Cached values are used when API is unavailable
2. **Config Fallback**: Default values from config when cache is empty
3. **Circuit Breaker**: Prevents repeated API calls when service is down
4. **Graceful Degradation**: Application continues working with default values

## Health Checks

Monitor Flagsmith integration health:

```bash
# Check overall health
curl /health

# Check Flagsmith specifically
curl /health/flagsmith
```

## Best Practices

1. **Always provide defaults**: Never rely on flags without fallback values
2. **Use descriptive names**: Make flag names self-explanatory
3. **Test fallbacks**: Ensure your app works when Flagsmith is unavailable
4. **Monitor usage**: Use health checks to monitor service availability
5. **Cache appropriately**: Balance freshness with performance

## Troubleshooting

### Common Issues

1. **Service Unavailable**: Check network connectivity and API URL
2. **Authentication Failed**: Verify environment key is correct
3. **Slow Response**: Increase cache TTL or check circuit breaker status
4. **Fallback Not Working**: Verify default flags are configured

### Debug Commands

```bash
# Clear Flagsmith cache
php artisan cache:forget flagsmith_*

# Check service health
php artisan health:flagsmith
```

## Security Considerations

1. **Environment Keys**: Keep environment keys secure and rotate regularly
2. **Network Security**: Use HTTPS for API communication
3. **Access Control**: Limit who can modify flags in Flagsmith dashboard
4. **Audit Logging**: Monitor flag changes and access patterns