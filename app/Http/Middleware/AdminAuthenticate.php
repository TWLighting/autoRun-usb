<?php

namespace App\Http\Middleware;

use Closure;
use Cache;

class AdminAuthenticate
{
    public function handle($request, Closure $next)
    {
        if (!$request->session()->get('isLogin', false)
            || $request->session()->get('accountType', '') != 'admin'
        ) {
            return response('Unauthorized.', 401);
        }

        // 单一登入
        $account = trim($request->session()->get('account'));
        $sigleSession = Cache::store('redis')->get('login/' . $account);
        if ($sigleSession && ($sigleSession != $request->session()->getId())) {
            $request->session()->flush();
            $request->session()->save();
            $ip = Cache::store('redis')->get('loginip/' . $account);
            if ($ip == 'Banned') {
                return response('Banned,', 401);
            }
            return response('Kicked,' . $ip, 401);
        }

        return $next($request);
    }
}
