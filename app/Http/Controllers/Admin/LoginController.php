<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Libraries\NotificationHelper;
use App\Libraries\Functions;
use DB;
use Cache;

class LoginController extends AdminController
{

    public function login(Request $request)
    {
        if ($request->session()->get('isLogin', false)) {
            $request->session()->flush();
            $request->session()->save();
        }

        $this->validate($request, [
            'account' => 'required',
            'password' => 'required',
            'verifica' => 'required|size:4',
        ]);

        $account = strtolower(trim($request->input('account')));
        $password = Functions::rsaDecrypt($request->input('password'));
        $verifica = $request->input('verifica');
        $ip = $request->ip();

        $accountObject = DB::table('account AS a')
            ->select([
                'a.*', 'a2.account AS top_account',
            ])
            ->where('a.status', '1')
            ->where('a.frequence', '<', '5')
            ->where('a.account', $account)
            ->leftJoin('account AS a2', 'a.top_account_id', '=', 'a2.id')
            ->first();


        if (!$accountObject) {
            return $this->presenter->json([], "无此账号", 2);
        }

        if (!password_verify($password, $accountObject->password)) {
            $this->login_fail($accountObject, "密码错误，账号：$account ，IP：$ip");
            return $this->presenter->json([], "登入失败，请重发验证码再尝试", 2);
        }

        if (!$accountObject->is_admin && strtolower($accountObject->telegram_code) != strtolower($verifica)) {
            $this->login_fail($accountObject, "验证码输入错误，账号：$account ，IP：$ip");
            return $this->presenter->json([], "验证码输入错误，请重发验证码再尝试", 3);
        }

        // update account
        DB::table('account')
            ->where('id', $accountObject->id)
            ->update([
                'login_ip' => $ip,
                'last_login_time' => DB::raw('NOW()'),
                'telegram_code' => null,
                'frequence' => 0
            ]);

        $region = Functions::getRegionFromIp($ip);

        // insert DB log
        DB::table('login_log')->insert([
            'account' => $account,
            'user_login_ip' => $ip,
            'province' => ($region['region'] != 'XX' && $region['region'] != '') ? $region['region'] : null,
            'city' => ($region['city'] != 'XX' && $region['city'] != '') ? $region['city'] : null,
        ]);

        if ($accountObject->is_admin) {
            $request->session()->put('accountType', 'admin');
        } else {
            $request->session()->put('accountType', 'user');
        }

        $request->session()->put('accountId', $accountObject->id);
        $request->session()->put('topAccountId', $accountObject->top_account_id);
        $request->session()->put('topAccount', $accountObject->top_account);
        $request->session()->put('permission', $accountObject->permission);
        $request->session()->put('telegram_path', $accountObject->telegram_path);
        $request->session()->put('telegram_chatid', $accountObject->telegram_chatid);
        $request->session()->put('isLogin', true);
        $request->session()->put('account', $account);
        $request->session()->save();
        Cache::store('redis')->forever('login/' . $account, $request->session()->getId());
        Cache::store('redis')->forever('loginip/' . $account, $ip);

        $data = [
            'account' => $account,
            'accountType' => $request->session()->get('accountType'),
            'accountId' => $accountObject->id,
        ];
        NotificationHelper::telegramSendmsg("[转速快] 登入成功，账号：$account ，IP：$ip", $accountObject->telegram_path, $accountObject->telegram_chatid);
        return $this->presenter->json($data, "成功", 1);
    }

    public function checkStatus(Request $request)
    {
        $data = ['status' => true];
        if ($request->session()->get('isLogin', false)) {
            $data['account'] = $request->session()->get('account', '');
            $data['accountType'] = $request->session()->get('accountType', '');
            $data['accountId'] = $request->session()->get('accountId', '');
            $data['permission'] = $request->session()->get('permission', 0);
            $data['topAccount'] = $request->session()->get('topAccount', '');
            return $this->presenter->json($data, "成功", 1);
        }
        $data['status'] = false;
        return $this->presenter->json($data, "未登入", 1);
    }

    public function logout(Request $request)
    {
        if (!$request->session()->get('isLogin', false)) {
            return $this->presenter->json([], "未登入", 1);
        }

        $request->session()->flush();
        $request->session()->save();

        return $this->presenter->json([], "登出成功", 1);
    }

    public function obtain(Request $request)
    {
        $this->validate($request, [
            'account' => 'required',
        ]);
        $account = $request->input('account');

        $accountObject = DB::table('account')
            ->where('status', '1')
            ->where('account', $account)->first();

        if (!$accountObject) {
            return $this->presenter->json([], "无此账号", 2);
        }

        if (empty($accountObject->telegram_chatid) || empty($accountObject->telegram_path)) {
            return $this->presenter->json([], "未配置验证", 2);
        }
        $telegram_code = Functions::randtext(4);
        $msg = "账号：$account ，您的登入验证码是：" . $telegram_code;

        DB::table('account')
            ->where('id', $accountObject->id)
            ->update(['telegram_code' => $telegram_code]);

        $telegramSendmsg = NotificationHelper::telegramSendmsg($msg, $accountObject->telegram_path, $accountObject->telegram_chatid, 5);

        if (!$telegramSendmsg) {
            return $this->presenter->json([], "验证码发送失败", 1);
        }

        return $this->presenter->json([], "成功", 1);

    }


    private function login_fail($accountObject, $msg)
    {
        DB::table('account')
            ->where('id', $accountObject->id)
            ->update([
                'telegram_code' => null,
                'frequence' => DB::raw('frequence+1')
            ]);
        $msg = "[转速快] 尝试登入失败：" . $msg;
        NotificationHelper::telegramSendmsg($msg, $accountObject->telegram_path, $accountObject->telegram_chatid);
    }

}
