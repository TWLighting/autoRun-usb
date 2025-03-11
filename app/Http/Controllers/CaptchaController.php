<?php

namespace App\Http\Controllers;

use App\Libraries\NotificationHelper;
use Illuminate\Http\Request;
use DB;
use Log;

class CaptchaController extends Controller
{

    public function solve(Request $request)
    {
        set_time_limit(120);
        $this->validate($request, [
            'card_no' => 'required',
            'captcha_base64' => 'required',
        ]);

        // 发送通知
        $msg = sprintf('验证码请求，卡号 [%s]', $request->input('card_no'));
        Log::channel('business_slack')->info($msg);
        NotificationHelper::telegramSendmsg($msg, '/bot477394263:AAHJfyyzcWc78nCW-yF2OUL9d8-iuzSLy_M/sendMessage', '-321563703');

        $id = DB::table('captcha_code')->insertGetId([
            'card_no' => $request->input('card_no'),
            'captcha_base64' => $request->input('captcha_base64'),
        ]);

        for ($i = 0; $i < 120; $i++) {
            sleep(1);
            $data = DB::table('captcha_code')
                ->whereNotNull('code')
                ->where('id', $id)
                ->first();
            if (!empty($data) && $data->code) {
                $result = [
                    'code' => $data->code,
                ];
                return $this->presenter->json($result, "成功", 1);
            }
        }

        return $this->presenter->json([], "超時", 2);
    }
}
