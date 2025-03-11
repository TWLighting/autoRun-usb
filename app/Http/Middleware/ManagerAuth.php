<?php

namespace App\Http\Middleware;

use Closure;
use Cache;

class ManagerAuth
{
    const PURVIEW = ['admin', 'user'];

    public function handle($request, Closure $next)
    {
        if ($request->session()->get('permission', false) !== 1) {
            return response('Unauthorized.', 401);
        }

        return $next($request);
    }
}
