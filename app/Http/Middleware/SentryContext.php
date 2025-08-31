<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use function Sentry\configureScope;
use Sentry\State\Scope;

class SentryContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldAddContext()) {
            $this->addRequestContext($request);
        }

        return $next($request);
    }

    /**
     * Check if Sentry context should be added.
     */
    protected function shouldAddContext(): bool
    {
        return config('deployment.monitoring.sentry.enabled', false) && 
               !empty(config('sentry.dsn'));
    }

    /**
     * Add request-specific context to Sentry.
     */
    protected function addRequestContext(Request $request): void
    {
        configureScope(function (Scope $scope) use ($request): void {
            // Add request information
            $scope->setContext('request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
            ]);

            // Add route information if available
            if ($request->route()) {
                $scope->setContext('route', [
                    'name' => $request->route()->getName(),
                    'action' => $request->route()->getActionName(),
                    'parameters' => $request->route()->parameters(),
                ]);
            }

            // Add session information (without sensitive data)
            if ($request->hasSession()) {
                $scope->setContext('session', [
                    'id' => $request->session()->getId(),
                    'csrf_token' => $request->session()->token(),
                ]);
            }

            // Add tenant context from request
            $this->addTenantContextFromRequest($scope, $request);

            // Add user context if authenticated
            if (Auth::check()) {
                $this->addUserContext($scope, Auth::user());
            }
        });
    }

    /**
     * Add tenant context extracted from request.
     */
    protected function addTenantContextFromRequest(Scope $scope, Request $request): void
    {
        // Extract tenant from subdomain
        $subdomain = $this->extractSubdomain($request);
        if ($subdomain) {
            $scope->setTag('tenant_subdomain', $subdomain);
        }

        // Extract tenant from URL parameters if present
        if ($request->has('tenant_id')) {
            $scope->setTag('tenant_id', $request->get('tenant_id'));
        }

        // Extract restaurant context if present in route
        if ($request->route() && $request->route()->hasParameter('restaurant')) {
            $restaurantId = $request->route()->parameter('restaurant');
            $scope->setTag('restaurant_id', $restaurantId);
            $scope->setContext('restaurant', ['id' => $restaurantId]);
        }
    }

    /**
     * Add user context to Sentry scope.
     */
    protected function addUserContext(Scope $scope, $user): void
    {
        $scope->setUser([
            'id' => $user->id,
            'email' => $user->email ?? null,
            'username' => $user->name ?? null,
        ]);

        // Add user roles if available
        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames()->toArray();
            $scope->setTag('user_roles', implode(',', $roles));
        }

        // Add restaurant association if user has one
        if (isset($user->restaurant_id)) {
            $scope->setTag('user_restaurant_id', $user->restaurant_id);
        }

        // Add user type context
        if (isset($user->user_type)) {
            $scope->setTag('user_type', $user->user_type);
        }
    }

    /**
     * Extract subdomain from request.
     */
    protected function extractSubdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $baseDomain = config('deployment.subdomain.base_domain', 'susankshakya.com.np');
        
        if (str_ends_with($host, $baseDomain)) {
            $subdomain = str_replace('.' . $baseDomain, '', $host);
            
            // Remove app prefix if present
            $appPrefix = config('deployment.subdomain.app_prefix', 'restant');
            if (str_starts_with($subdomain, $appPrefix . '.')) {
                return substr($subdomain, strlen($appPrefix) + 1);
            }
            
            return $subdomain;
        }

        return null;
    }
}