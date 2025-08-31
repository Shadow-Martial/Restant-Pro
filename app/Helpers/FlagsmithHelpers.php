<?php

use App\Facades\Flagsmith;

if (!function_exists('feature_flag')) {
    /**
     * Get a feature flag value
     */
    function feature_flag(string $flagName, $defaultValue = false, ?string $identity = null)
    {
        if (!config('flagsmith.enabled')) {
            return $defaultValue;
        }

        return Flagsmith::getFlag($flagName, $defaultValue, $identity);
    }
}

if (!function_exists('feature_enabled')) {
    /**
     * Check if a feature is enabled
     */
    function feature_enabled(string $flagName, ?string $identity = null): bool
    {
        if (!config('flagsmith.enabled')) {
            return config("flagsmith.default_flags.{$flagName}", false);
        }

        return Flagsmith::isEnabled($flagName, $identity);
    }
}

if (!function_exists('user_feature_enabled')) {
    /**
     * Check if a feature is enabled for a specific user
     */
    function user_feature_enabled(string $flagName, $user = null): bool
    {
        $identity = null;
        
        if ($user) {
            $identity = is_object($user) ? $user->id : $user;
        } elseif (auth()->check()) {
            $identity = auth()->id();
        }

        return feature_enabled($flagName, $identity ? (string) $identity : null);
    }
}

if (!function_exists('tenant_feature_enabled')) {
    /**
     * Check if a feature is enabled for current tenant context
     */
    function tenant_feature_enabled(string $flagName): bool
    {
        // Get tenant identifier from session or request
        $tenantId = session('tenant_id') ?? request()->header('X-Tenant-ID');
        
        if ($tenantId) {
            return feature_enabled($flagName, "tenant_{$tenantId}");
        }

        return feature_enabled($flagName);
    }
}