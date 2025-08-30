<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupIsDone
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::user()->hasRole('admin')) {
            if (file_exists(storage_path('verified')) || config('settings.is_demo', false)) {
                return $next($request);
            } else {
                return redirect(route('systemstatus'));
            }
        }

        return $next($request);

    }
}
