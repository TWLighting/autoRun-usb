<?php

namespace App\Http\Controllers\Admin;

use App\Libraries\Functions;
use App\Events\NotificationEvent;
use App\Libraries\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Cache;
use DB;
use Event;
use Log;
use App\Libraries\Verify;

class AccountController extends AdminController
{
    public function getList(Request $request)
    {
        $account = $request->input('account');
        $data = DB::table('account AS a')
            ->select([
                'a.id', 'a.account', 'a.name',
                'a.is_admin', 'a.login_ip', 'a.status',
                'a.last_login_time',
                'a.telegram_path', 'a.telegram_chatid',
                'a.telegram_chatid_trans', 'a.callback_url',
                'a.permission', 'a2.account AS top_account',
            ])
            ->leftJoin('account AS a2', 'a.top_account_id', '=', 'a2.id')
            ->orderBy('a.top_account_id', 'asc')
            ->orderBy('a.id', 'asc');

        if (!Functions::isAdmin($request)) {
            $data->where('a.top_account_id', $request->session()->get('topAccountId'));
        }
        if ($request->filled('account')) {
            $data->where('a.account', 'like', $account."%");
        }
        if ($request->filled('status')) {
            $data->where('a.status', $request->input('status'));
        }

        $data = $data->paginate($request->input('perpage', 20));

        return $this->presenter->json($data, "成功", 1);
    }

    public function edit(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'name' => 'required',
            'operating_pw' => 'required'
        ]);

        $id = $request->input('id');
        $name = $request->input('name');
        $newPassword = $request->input('password');
        $pay_password = $request->input('pay_password');

        if ($request->input('operating_pw') != config('admin.operating_pw')) {
            return $this->presenter->json([], "操作密码错误", 2);
        }

        $updateList = ['name' => $name];

        if ($request->filled('telegram_path')) {
            $updateList['telegram_path'] = $request->input('telegram_path');
        }
        if ($request->filled('telegram_chatid')) {
            $updateList['telegram_chatid'] = $request->input('telegram_chatid');
        }
        if ($request->filled('callback_url')) {
            $callback_url = $request->input('callback_url');
            if ($callback_url == '') {
                $callback_url = null;
            }
            $updateList['callback_url'] = $callback_url;
        }
        if ($request->has('telegram_chatid_trans')) {
            $telegram_chatid_trans = $request->input('telegram_chatid_trans');
            if ($telegram_chatid_trans == '') {
                $telegram_chatid_trans = null;
            }
            $updateList['telegram_chatid_trans'] = $telegram_chatid_trans;
        }

        if ($request->filled('permission')) {
            $updateList['permission'] = $request->input('permission');
        }

        if ($newPassword) {
            $updateList['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        if ($pay_password) {
            $updateList['pay_password'] = password_hash($pay_password, PASSWORD_DEFAULT);
        }

        DB::table('account')
            ->where('id', $id)
            ->update($updateList);
        return $this->presenter->json([], "成功", 1);
    }

    public function changePw(Request $request)
    {
        $this->validate($request, [
            'password' => 'required',
            'newPassword' => 'required'
        ]);

        $id = $request->session()->get('accountId');
        $oldPassword = $request->input('password');
        $newPassword = $request->input('newPassword');

        $accountObject = DB::table('account')
            ->where('status', '1')
            ->where('id', $id)->first();

        if (!password_verify($oldPassword, $accountObject->password)) {
            return $this->presenter->json([], "舊密碼錯誤", 2);
        }

        DB::table('account')
            ->where('id', $id)
            ->update(['password' => password_hash($newPassword, PASSWORD_DEFAULT)]);
        return $this->presenter->json([], "成功", 1);
    }

    public function changeStatus(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'status' => [
                'required',
                Rule::in([0, 1]),
            ],
        ]);

        $id = $request->input('id');
        $status = $request->input('status');

        $update = DB::table('account')
            ->where('id', $id);

        if (!Functions::isAdmin($request)) {
            if ($request->session()->get('permission') != 1) {
                return $this->presenter->json([], "权限不足", 2);
            }
            $update->where('top_account_id', $request->session()->get('topAccountId'));
        }
        $update->update(['status' => $status]);

        if ($status == 0) {
            $account = DB::table('account')
                ->select('account')
                ->where('id', $id)
                ->first();
            if ($account) {
                Cache::store('redis')->forever('login/' . $account->account, 'Admin Banned User');
                Cache::store('redis')->forever('loginip/' . $account->account, 'Banned');
            }
        }

        return $this->presenter->json([], "成功", 1);
    }

    public function insert(Request $request)
    {
        $this->validate($request, [
            'is_admin' => 'required',
            'account' => 'required',
            'password' => 'required',
            'name' => 'required',
            'pay_password' => 'required',
            'telegram_path' => 'required_if:is_admin,0',
            'telegram_chatid' => 'required_if:is_admin,0',
            'permission' => 'required_if:is_admin,0',
        ]);

        $top_account = $request->input('top_account', '');
        $permission = ($request->input('is_admin') == 1) ? 1 : $request->input('permission', 0);

        $data = $request->only(['is_admin', 'account', 'password', 'name', 'pay_password', 'telegram_path', 'telegram_chatid']);
        $checkExist = DB::table('account')
            ->select(['id'])
            ->where('account', $data['account'])
            ->first();

        if ($checkExist) {
            return $this->presenter->json([], "账号已存在", 2);
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['pay_password'] = password_hash($data['pay_password'], PASSWORD_DEFAULT);
        $data['md5_key'] = Functions::randtext(32);
        $data['des_key'] = Functions::randtext(32);
        $data['telegram_path'] = $request->input('telegram_path', null);
        $data['telegram_chatid'] = $request->input('telegram_chatid', null);
        $data['telegram_chatid_trans'] = $request->input('telegram_chatid_trans', null);
        $data['callback_url'] = $request->input('callback_url', null);

        if ($data['is_admin'] == 1) {
            $data['telegram_path'] = '/bot477394263:AAHJfyyzcWc78nCW-yF2OUL9d8-iuzSLy_M/sendMessage';
            $data['telegram_chatid'] = '-321563703';
            $data['telegram_chatid_trans'] = '-321563703';
        }
        $data['permission'] = $permission;
        $data['top_account_id'] = 0;

        if ($top_account) {
            $top_account_id = DB::table('account')->select(['id'])
                ->where('account', $top_account)
                ->whereRaw('id = top_account_id')
                ->first();

            if (!$top_account_id) {
                return $this->presenter->json([], "主商户错误", 2);
            }
            $data['top_account_id'] = $top_account_id->id;
        }

        $id = DB::table('account')->insertGetId($data);
        if (!$top_account) {
            DB::table('account')->where('id', $id)->update(['top_account_id' => $id]);
        }
        return $this->presenter->json(['id' => $id], "成功", 1);
    }

    public function callNotification(Request $request)
    {
        $this->validate($request, [
            'account' => 'required',
            'message' => 'required',
        ]);

        $message = $request->input('message');
        $account = $request->input('account');
        $log = sprintf('%s，发送通知给:%s，内容:%s', $request->session()->get('account'), $account, $message);
        Log::channel('notify')->info($log);
        Event::fire(new NotificationEvent($message, $account));

        // telegram
        $accountObject = DB::table('account')
            ->select(['telegram_path', 'telegram_chatid'])
            ->where('account', $account)
            ->first();

        if ($accountObject) {
            if ($accountObject->telegram_path && $accountObject->telegram_chatid) {
                NotificationHelper::telegramSendmsg($message, $accountObject->telegram_path, $accountObject->telegram_chatid);
            }
        }
        return $this->presenter->json([], "成功", 1);
    }

    public function viewKey(Request $request)
    {
        $this->validate($request, ['payPassword' => 'required']);

        $accountId = $request->session()->get('accountId', '');

        if(!Verify::verificaPayPassword($accountId, $request->input('payPassword'))){
            return $this->presenter->json([], "交易密码错误", 2);
        }

        $data = DB::table('account')->select(['md5_key', 'des_key'])
            ->where('id', $accountId)
            ->first();

        return $this->presenter->json($data, "成功", 1);
    }

    /**
     * 商户新增子帐号
     */
    public function userInsert(Request $request)
    {
        $this->validate($request, [
            'account' => 'required',
            'password' => 'required',
            'name' => 'required',
            'pay_password' => 'required',
            'permission' => [
                'required',
                Rule::in([0, 1]),
            ],
        ]);

        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }
        $data = $request->only(['permission', 'account', 'password', 'name', 'pay_password']);

        // 检查账号重复
        $checkExist = DB::table('account')
            ->select(['id'])
            ->where('account', $data['account'])
            ->first();

        if ($checkExist) {
            return $this->presenter->json([], "账号已存在", 2);
        }

        $top_account_id = $request->session()->get('topAccountId');
        // 取得主商户资料
        $mainAccount = DB::table('account')
            ->select(['id', 'md5_key', 'des_key', 'telegram_path', 'telegram_chatid'])
            ->where('id', $request->session()->get('topAccountId'))
            ->first();

        if (!$mainAccount) {
            return $this->presenter->json([], "主商户异常", 2);
        }

        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['pay_password'] = password_hash($data['pay_password'], PASSWORD_DEFAULT);
        $data['md5_key'] = $mainAccount->md5_key;
        $data['des_key'] = $mainAccount->des_key;
        $data['telegram_path'] = $mainAccount->telegram_path;
        $data['telegram_chatid'] = $mainAccount->telegram_chatid;
        $data['callback_url'] = $mainAccount->callback_url;
        $data['top_account_id'] = $top_account_id;

        $id = DB::table('account')->insertGetId($data);

        return $this->presenter->json(['id' => $id], "成功", 1);

    }

    /**
     * 商户编辑子帐号
     */
    public function userEdit(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'name' => 'required',
            'permission' => 'required',
        ]);

        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }

        $id = $request->input('id');

        $updateList = [
            'name' => $request->input('name'),
            'permission' => $request->input('permission'),
        ];

        if ($request->filled('password')) {
            $updateList['password'] = password_hash($request->input('password'), PASSWORD_DEFAULT);
        }
        if ($request->filled('pay_password')) {
            $updateList['pay_password'] = password_hash($request->input('password'), PASSWORD_DEFAULT);
        }

        // 主商户不可被修改 id != top_account_id
        DB::table('account')
            ->where('id', $id)
            ->where('top_account_id', $request->session()->get('topAccountId'))
            ->whereRaw('id != top_account_id')
            ->update($updateList);

        return $this->presenter->json([], "成功", 1);
    }
}
