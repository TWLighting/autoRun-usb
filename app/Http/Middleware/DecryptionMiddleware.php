<?php

namespace App\Http\Middleware;

use App\Libraries\Cryptology;
use Closure;

class DecryptionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $input = $request->all();
        if (empty($input['data'])) {
            return response()->json(['data' => '参数异常'], 422);
        }
        $result = Cryptology::decryption($input['data']);
        if (!is_array($result)) {
            return response()->json(['data' => '参数异常'], 422);
        }

        $request->replace($result);
        return $next($request);
    }
}
