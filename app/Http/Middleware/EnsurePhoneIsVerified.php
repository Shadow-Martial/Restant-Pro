<?php

namespace App\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneIsVerified
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && config('settings.enable_sms_verification')) {
            if (! $request->user()->hasVerifiedPhone()) {
                return redirect()->route('phoneverification.notice');
            }
        }

        return $next($request);
    }
}
