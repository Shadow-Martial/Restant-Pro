<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\DeploymentService;
use Symfony\Component\HttpFoundation\Response;

class DeploymentEnvironmentMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Set deployment context in request
        $request->attributes->set('deployment.environment', DeploymentService::getCurrentEnvironment());
        $request->attributes->set('deployment.subdomain', DeploymentService::getSubdomain());
        $request->attributes->set('deployment.domain', DeploymentService::getDomain());
        
        // Add environment headers for debugging (only in non-production)
        $response = $next($request);
        
        if (!DeploymentService::isProduction() && config('app.debug')) {
            $response->headers->set('X-Deployment-Environment', DeploymentService::getCurrentEnvironment());
            $response->headers->set('X-Deployment-Subdomain', DeploymentService::getSubdomain());
        }
        
        return $response;
    }
}