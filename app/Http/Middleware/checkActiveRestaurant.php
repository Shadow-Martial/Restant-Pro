<?php

namespace App\Http\Middleware;

use App\Restorant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class checkActiveRestaurant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tmp = explode('/', URL::current());
        $alias = end($tmp);
        $restorant = Restorant::where('subdomain', $alias)->first();

        if ($restorant->active == 1) {
            return $next($request);
        } else {
            return redirect('/');
        }
    }
}
