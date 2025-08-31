<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceModeMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if maintenance mode is enabled via feature flag
        if (feature_enabled('maintenance_mode')) {
            // Allow access for admin users
            if (auth()->check() && auth()->user()->hasRole('admin')) {
                return $next($request);
            }

            // Show maintenance page for regular users
            return response()->view('maintenance', [], 503);
        }

        return $next($request);
    }
}