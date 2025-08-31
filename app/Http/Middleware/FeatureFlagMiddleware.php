<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureFlagMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $flagName, string $redirectRoute = null): Response
    {
        // Check if feature is enabled for current user/tenant
        $isEnabled = $this->checkFeatureFlag($flagName, $request);

        if (!$isEnabled) {
            if ($redirectRoute) {
                return redirect()->route($redirectRoute);
            }

            // Return 404 if no redirect route specified
            abort(404);
        }

        return $next($request);
    }

    /**
     * Check feature flag with appropriate context
     */
    private function checkFeatureFlag(string $flagName, Request $request): bool
    {
        // Try user-specific flag first
        if (auth()->check()) {
            $userFlag = user_feature_enabled($flagName);
            if ($userFlag !== null) {
                return $userFlag;
            }
        }

        // Try tenant-specific flag
        $tenantId = session('tenant_id') ?? $request->header('X-Tenant-ID');
        if ($tenantId) {
            return feature_enabled($flagName, "tenant_{$tenantId}");
        }

        // Fall back to global flag
        return feature_enabled($flagName);
    }
}