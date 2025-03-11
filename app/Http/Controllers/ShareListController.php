<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use DB;
use Log;

class ShareListController extends Controller
{

    public function register(Request $request)
    {
        $this->validate($request, [
            'account' => 'required',
            'password' => 'required',
        ]);

        $account = $request->input('account');
        $password = $request->input('password');

        Log::channel('sharelist')->info('[register] 接收请求 account: ' . $account);

        $accountObject = DB::table('account')
            ->where('status', '1')
            ->where('account', $account)
            ->first();

        if (!$accountObject) {
            Log::channel('sharelist')->info('[register] 无此账号 account: ' . $account);
            return $this->presenter->json([], "无此账号", 3);
        }

        if (!password_verify($password, $accountObject->password)) {
            Log::channel('sharelist')->info('*[register] 密码错误 account: ' . $account);
            return $this->presenter->json([], "密码错误", 3);
        }

        DB::table('share_list_hashcode')
            ->where('account_id', $accountObject->id)
            ->delete();

        $hashcode = str_random(8);
        for ($x = 0; $x <= 10; $x++) {
            $checker = DB::table('share_list_hashcode')
                ->where('hashcode', $hashcode)
                ->first();
            if (!$checker) {
                break;
            }
            if ($x == 10) {
                return $this->presenter->json([], "系统异常，产生ID失败", 2);
            }
            $hashcode = str_random(8);
        }

        DB::table('share_list_hashcode')
            ->insert([
                'account_id' => $accountObject->id,
                'top_account_id' => $accountObject->top_account_id,
                'hashcode' => $hashcode,
            ]);

        return $this->presenter->json(['hashcode' => $hashcode], "登入成功", 1);
    }

    public function request(Request $request)
    {
        $this->validate($request, [
            'account' => 'required',
            'password' => 'required',
            'hashcode' => 'required',
            'ip' => 'required',
        ]);

        $account = $request->input('account');
        $password = $request->input('password');
        $hashcode = $request->input('hashcode');

        Log::channel('sharelist')->info('[request] 接收请求 account: ' . $account);

        $accountObject = DB::table('account')
            ->where('status', '1')
            ->where('account', $account)
            ->first();

        if (!$accountObject) {
            Log::channel('sharelist')->info('[request] 无此账号 account: ' . $account);
            return $this->presenter->json([], "无此账号", 3);
        }

        if (!password_verify($password, $accountObject->password)) {
            Log::channel('sharelist')->info('*[request] 密码错误 account: ' . $account);
            return $this->presenter->json([], "密码错误", 3);
        }

        $shareList = DB::table('share_list_hashcode')
            ->where('top_account_id', $accountObject->top_account_id)
            ->where('hashcode', $hashcode)
            ->first();

        if(!$shareList) {
            return $this->presenter->json([], "无效ID", 2);
        }

        DB::table('share_list')
            ->insert([
                'account_id' => $accountObject->id,
                'top_account_id' => $accountObject->top_account_id,
                'msg' => ' ',
                'ip' => $request->input('ip'),
                'status' => 0,
            ]);

        return $this->presenter->json([], "请求成功，请等候连接", 1);
    }

    public function get(Request $request)
    {
        $this->validate($request, [
            'account' => 'required',
            'password' => 'required',
        ]);

        $account = $request->input('account');
        $password = $request->input('password');

        Log::channel('sharelist')->info('[get] 接收请求 account: ' . $account);

        $accountObject = DB::table('account')
            ->where('status', '1')
            ->where('account', $account)
            ->first();

        if (!$accountObject) {
            Log::channel('sharelist')->info('[get] 无此账号 account: ' . $account);
            return $this->presenter->json([], "无此账号", 3);
        }

        if (!password_verify($password, $accountObject->password)) {
            Log::channel('sharelist')->info('*[get] 密码错误 account: ' . $account);
            return $this->presenter->json([], "密码错误", 3);
        }

        $data = null;
        DB::transaction(function () use (&$data, $account) {
            $data = DB::table('share_list')
                ->where('status', '0')
                ->where('user_id', $account)
                ->orderBy('id', 'asc')
                ->limit(1)
                ->lockForUpdate()
                ->first();

            if ($data) {
                $updated = DB::table('share_list')
                    ->where('id', $data->id)
                    ->where('status', 0)
                    ->update(['status' => '4']);
            }
        });

        if (empty($data)) {
            return $this->presenter->json([], "無资料", 2);
        }

        $return = [
            "id" => $data->id,
            "msg" => $data->msg,
            "ip" => $data->ip,
        ];

        return $this->presenter->json($return, "成功", 1);
    }

    public function update(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer',
            'status' => [
                'required',
                Rule::in([1, 2]),
            ],
        ]);
        $id = $request->input('id');

        Log::channel('sharelist')->info('[update] 接收资料: ' . json_encode($request->input(), 320));
        $updated = DB::table('share_list')
            ->where('id', $id)
            ->where('status', '4')
            ->update([
                'status' => $request->input('status'),
                'attach' => $request->input('attach'),
            ]);
        if (!$updated) {
            // 失败时 log 写进 attach
            /*
            DB::table('share_list')
                ->where('id', $id)
                ->update([
                    'attach' => json_encode($request->input(), 320),
                ]);
            */
            return $this->presenter->json([], "失败", 2);
        }
        Log::channel('sharelist')->info(sprintf("[update] 更新状态成功 id [%s]", $id));
        return $this->presenter->json([], "成功", 1);
    }
}
