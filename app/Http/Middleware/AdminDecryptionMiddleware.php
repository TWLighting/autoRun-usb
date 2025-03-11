<?php

namespace App\Http\Middleware;

use App\Libraries\Functions;
use Closure;
use Carbon\Carbon;

class AdminDecryptionMiddleware
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

        // GET 不解密
        if ($request->isMethod('get')) {
            return $next($request);
        }

        $input = $request->all();
        // 没参数 不解密
        if (empty($input)) {
            return $next($request);
        }

        // 只有page的时后 不解密
        if (count($input) === 1 && isset($input['page'])) {
            return $next($request);
        }

        // 有参数 但不是 加密结果 全部阻挡
        if (empty($input['data']) || empty($input['k']) || empty($input['s']) || empty($input['i'])) {
            return response('参数异常。', 422);
        }

        $key = Functions::rsaDecrypt($input['k']);
        if (!$key) {
            return response('参数异常.', 422);
        }

        $result = Functions::decryptAES($input['data'], $key, $input['s'], $input['i']);
        if (!$result) {
            return response('data参数异常.', 422);
        }
        $result = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response('json参数异常.', 422);
        }
        if (empty($result['apipostt']) || Carbon::now()->diffInSeconds($result['apipostt']) > 60) {
            return response('超时', 422);
        }

        $request->replace($result);
        return $next($request);

    }
}
