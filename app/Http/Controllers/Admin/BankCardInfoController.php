<?php

namespace App\Http\Controllers\Admin;

use App\Libraries\Functions;
use App\Libraries\NotificationHelper;
use Illuminate\Http\Request;
use DB;
use App\Libraries\Verify;

class BankCardInfoController extends AdminController
{
    public function getList(Request $request)
    {
        $data = DB::table('bank_card_info AS bci')
            ->select([
                'a.account', 'bci.id', 'bci.usb_key_id', 'bci.bank_name',
                'bci.card_no', 'bci.login_account', 'bci.acc_name', 'bci.balance',
                'bci.status', 'bci.account_id', 'bci.notify_enable', 'bci.login_server_id',
                'uk.name', 'up.index', 'ud.hashcode', 'ud.nickname AS ud_name'
            ])
            ->join('account AS a', 'bci.account_id', '=', 'a.id')
            ->leftjoin('usb_key AS uk', 'bci.usb_key_id', '=', 'uk.id')
            ->leftjoin('usb_port AS up', 'up.usb_uid', '=', 'uk.usb_uid')
            ->leftjoin('usb_device AS ud', 'ud.id', '=', 'up.usb_device_id')
            ->orderBy('bci.id', 'desc');
        if (!Functions::isAdmin($request)) {
            $data->where('bci.account_id', $request->session()->get('topAccountId'));
        } elseif ($request->filled('account')) {
            $data->where('a.account', $request->input('account'));
        }

        if ($request->filled('card_no')) {
            $data->where('bci.card_no', 'like', $request->input('card_no') . '%');
        }
        if ($request->filled('acc_name')) {
            $data->where('bci.acc_name', $request->input('acc_name'));
        }
        if ($request->filled('status')) {
            $data->where('bci.status', $request->input('status'));
        }


        $data = $data->paginate($request->input('perpage', 20));
        $data->getCollection()->transform(function ($value) {
            $value->balance = round($value->balance, 2);
            return $value;
        });
        return $this->presenter->json($data, "成功", 1);
    }

    public function creatBankCard(Request $request)
    {
        $this->validate($request, [
            'bank_name' => 'required',
            'card_no' => 'required',
            'login_account' => 'required_if:bank_name,中国建设银行',
            'acc_name' => 'required',
            'login_pwd' => 'required',
            'ukey_pwd' => 'required',
            'pay_pwd' => 'required',
            'operating_pw' => 'required',
        ]);

        if (!Functions::isAdmin($request)) {
            $accountId = $request->session()->get('accountId', '');

            if (!Verify::verificaPayPassword($accountId, $request->input('operating_pw'))) {
                return $this->presenter->json([], "交易密码错误", 2);
            }
        } else {
            if ($request->input('operating_pw') != config('admin.operating_pw')) {
                return $this->presenter->json([], "操作密码错误", 2);
            }
        }

        $input_data = $request->input();

        if (empty($input_data['usb_key_id'])) {
            $input_data['usb_key_id'] = null;
        }

        if ($this->checkCardNoExist($input_data['card_no'])) {
            return $this->presenter->json([], "卡号已存在", 2);
        }
        if ($this->checkUKeyBank($input_data['usb_key_id'], $request->input('bank_name'))) {
            return $this->presenter->json([], "U盾不允许绑定不同银行", 2);
        }

        $input_data['login_pwd'] = Functions::rsaDecrypt($input_data['login_pwd']);
        $input_data['ukey_pwd'] = Functions::rsaDecrypt($input_data['ukey_pwd']);
        $input_data['pay_pwd'] = Functions::rsaDecrypt($input_data['pay_pwd']);
        if (empty($input_data['login_pwd']) || empty($input_data['ukey_pwd']) || empty($input_data['pay_pwd'])) {
            return $this->presenter->json([], "密码异常，请通知管理员", 2);
        }

        // 取得 登入伺服器id
        $loginServer = DB::table('login_server')
            ->select('id')
            ->orderBy('total_used', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        if (!$loginServer) {
            return $this->presenter->json([], "服务器异常，请通知管理员", 2);
        }
        DB::table('login_server')
            ->where('id', $loginServer->id)
            ->increment('total_used');

        $inserted = DB::table('bank_card_info')->insertGetId([
            'account_id' => $request->session()->get('topAccountId'),
            'usb_key_id' => $input_data['usb_key_id'],
            'bank_name' => $input_data['bank_name'],
            'card_no' => $input_data['card_no'],
            'acc_name' => $input_data['acc_name'],
            'login_account' => $request->input('login_account', null),
            'login_pwd' => Functions::sign3des($input_data['login_pwd']),
            'ukey_pwd' => Functions::sign3des($input_data['ukey_pwd']),
            'pay_pwd' => Functions::sign3des($input_data['pay_pwd']),
            'balance' => 0,
            'status' => 1,
            'notify_enable' => 1,
            'login_server_id' => $loginServer->id,
        ]);

        $msg = sprintf(
            "银行卡新增\n卡号：%s\n姓名：%s\n编辑账号：%s",
            $input_data['card_no'],
            $input_data['acc_name'],
            $request->session()->get('account')
        );
        NotificationHelper::telegramSendmsg(
            $msg,
            $request->session()->get('telegram_path', ''),
            $request->session()->get('telegram_chatid', '')
        );
        return $this->presenter->json([], "新增成功", 1);
    }

    public function updateBankCard(Request $request)
    {
        $this->validate($request, [
            'operating_pw' => 'required',
            'id' => 'required',
            'bank_name' => 'required',
            'login_account' => 'required_if:bank_name,中国建设银行',
            'card_no' => 'required',
            'acc_name' => 'required',
        ]);
        $bank_name = $request->input('bank_name');

        if (!Functions::isAdmin($request)) {
            $accountId = $request->session()->get('accountId', '');

            if (!Verify::verificaPayPassword($accountId, $request->input('operating_pw'))) {
                return $this->presenter->json([], "交易密码错误", 2);
            }
        } else {
            if ($request->input('operating_pw') != config('admin.operating_pw')) {
                return $this->presenter->json([], "操作密码错误", 2);
            }
        }

        if ($this->checkCardNoExist($request->input('card_no'), $request->input('id'))) {
            return $this->presenter->json([], "卡号已存在", 2);
        }
        $usb_key_id = null;
        if ($request->filled('usb_key_id')) {
            $usb_key_id = $request->input('usb_key_id');
        }
        if ($this->checkUKeyBank($usb_key_id, $bank_name)) {
            return $this->presenter->json([], "U盾不允许绑定不同银行", 2);
        }

        $input_data = [
            'usb_key_id' => $usb_key_id,
            'bank_name' => $bank_name,
            'card_no' => $request->input('card_no'),
            'acc_name' => $request->input('acc_name'),
            'login_account' => $request->input('login_account', null),
            'last_modified_user' => $request->session()->get('account'),
        ];

        if ($request->filled('login_pwd')) {
            $input_data['login_pwd'] = Functions::rsaDecrypt($request->input('login_pwd'));
            if (empty($input_data['login_pwd'])) {
                return $this->presenter->json([], "登录密码异常，请通知管理员", 2);
            }
            $input_data['login_pwd'] = Functions::sign3des($input_data['login_pwd']);
        }
        if ($request->filled('ukey_pwd')) {
            $input_data['ukey_pwd'] = Functions::rsaDecrypt($request->input('ukey_pwd'));
            if (empty($input_data['ukey_pwd'])) {
                return $this->presenter->json([], "U盾密码异常，请通知管理员", 2);
            }
            $input_data['ukey_pwd'] = Functions::sign3des($input_data['ukey_pwd']);
        }
        if ($request->filled('pay_pwd')) {
            $input_data['pay_pwd'] = Functions::rsaDecrypt($request->input('pay_pwd'));
            if (empty($input_data['pay_pwd'])) {
                return $this->presenter->json([], "支付密码异常，请通知管理员", 2);
            }
            $input_data['pay_pwd'] = Functions::sign3des($input_data['pay_pwd']);
        }

        $data = DB::table('bank_card_info')
            ->where('id', $request->input('id'));

        // 不是管理者的话 只能修改自己的银行卡
        if (!Functions::isAdmin($request)) {
            $data->where('account_id', $request->session()->get('topAccountId'));
        }
        $data->update($input_data);

        $msg = sprintf(
            "银行卡编辑\n卡号：%s\n姓名：%s\n编辑账号：%s",
            $input_data['card_no'],
            $input_data['acc_name'],
            $request->session()->get('account')
        );
        NotificationHelper::telegramSendmsg(
            $msg,
            $request->session()->get('telegram_path', ''),
            $request->session()->get('telegram_chatid', '')
        );

        return $this->presenter->json([], "更新成功", 1);
    }

    public function getCardNo(Request $request)
    {
        $data = DB::table('bank_card_info')->select(['card_no' , 'acc_name']);
        if (!Functions::isAdmin($request) || $request->input('get_self', false)) {
            $data->where('account_id', $request->session()->get('topAccountId'));
        }
        $data = $data->where('status', 1)
            ->get();
        return $this->presenter->json($data, "成功", 1);
    }

    public function changeStatus(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'status' => 'required'
        ]);

        $id = $request->input('id');
        $status = $request->input('status');


        $data = DB::table('bank_card_info')->where('id', $id);

        if (!Functions::isAdmin($request)) {
            $data->where('account_id', $request->session()->get('topAccountId'));
        }
        $count = $data->update(['status' => $status]);

        // 修改成功 且 停用卡号 时  更新任务状态
        if ($count && $status == 0) {
            $bank_card = DB::table('bank_card_info')
                ->where('id', $id)
                ->first();
            $this->autorunJobChange($bank_card->card_no);
        }

        return $this->presenter->json([], "成功", 1);
    }

    public function changeNotifyStatus(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'notify_enable' => 'present'
        ]);

        $id = $request->input('id');
        $notify_enable = intval($request->input('notify_enable'));

        $data = DB::table('bank_card_info')
            ->where('id', $id);

        if (!Functions::isAdmin($request)) {
            $data->where('account_id', $request->session()->get('topAccountId'));
        }
        $result = $data->update(['notify_enable' => $notify_enable]);

        if (!$result) {
            return $this->presenter->json([], "失败", 2);
        }
        return $this->presenter->json([], "成功", 1);
    }

    public function deleteBankCard(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
        ]);

        $id = $request->input('id');
        $not_admin = !Functions::isAdmin($request);
        // 获取资料
        $data = DB::table('bank_card_info')->where('id', $id);
        if ($not_admin) {
            $data->where('account_id', $request->session()->get('topAccountId'));
        }
        $data = $data->first();
        if (!$data) {
            return $this->presenter->json([], "卡号不存在", 2);
        }

        // 备份
        $backup = DB::table('bank_card_deleted')->insert([
            'id' => $data->id,
            'account_id' => $data->account_id,
            'usb_key_id' => $data->usb_key_id,
            'bank_name' => $data->bank_name,
            'card_no' => $data->card_no,
            'acc_name' => $data->acc_name,
            'login_pwd' => $data->login_pwd,
            'ukey_pwd' => $data->ukey_pwd,
            'pay_pwd' => $data->pay_pwd,
            'balance' => $data->balance,
            'status' => $data->status,
            'deleted_user' => $request->session()->get('account'),
            'created_at' => $data->created_at,
            'updated_at' => $data->updated_at,
        ]);

        if (!$backup) {
            return $this->presenter->json([], "删除失败！", 2);
        }

        // 删除
        $deleted = DB::table('bank_card_info')->where('id', $id);
        if ($not_admin) {
            $deleted->where('account_id', $request->session()->get('topAccountId'));
        }
        $deleted = $deleted->delete();
        if (!$deleted) {
            return $this->presenter->json([], "删除失败。", 2);
        }

        // 删除成功 时  更新任务状态
        $this->autorunJobChange($data->card_no);

        return $this->presenter->json([], "成功", 1);
    }

    // 检查卡号是否重复
    private function checkCardNoExist($card_no, $id = 0)
    {
        $data = DB::table('bank_card_info')
            ->select(['id'])
            ->where('card_no', $card_no)
            ->where('id', '!=', $id)
            ->first();
        if ($data) {
            return true;
        }
        return false;
    }

    // 检查U盾是否同间银行
    private function checkUKeyBank($usb_key_id, $bank_name)
    {
        if(!$usb_key_id) {
            return false;
        }

        $data = DB::table('bank_card_info')
            ->selectRaw('DISTINCT(bank_name)')
            ->where('usb_key_id', $usb_key_id)
            ->first();
        if ($data && $data->bank_name != $bank_name) {
            return true;
        }
        return false;
    }

    private function autorunJobChange($card_no)
    {
        DB::table('autorun_job')
            ->where('card_no', $card_no)
            ->where('status', 0)
            ->update([
                'status' => -1,
                'attach' => '交易失败，卡号已停用'
            ]);
    }

    public function setLoginServer(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'login_server_id' => 'required',
            'operating_pw' => 'required',
        ]);

        if ($request->input('operating_pw') != config('admin.operating_pw')) {
            return $this->presenter->json([], "操作密码错误", 2);
        }

        $id = $request->input('id');
        $login_server_id = $request->input('login_server_id');

        $data = DB::table('login_server')
            ->select(['id'])
            ->where('id', $login_server_id)
            ->first();
        if (!$data) {
            return $this->presenter->json([], "失败，登录服务器异常", 2);
        }
        $result = DB::table('bank_card_info')
            ->where('id', $id)
            ->update(['login_server_id' => $login_server_id]);

        if (!$result) {
            return $this->presenter->json([], "失败", 2);
        }
        return $this->presenter->json([], "成功", 1);
    }

    public function getLoginServerList(Request $request)
    {

        $data = DB::table('login_server')
            ->select(['id', 'name', 'total_used'])
            ->get();

        return $this->presenter->json($data, "成功", 1);
    }

    /*
    public function viewPassword(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'operating_pw' => 'required'
        ]);
        if (!Functions::isAdmin($request)){
            $accountId = $request->session()->get('accountId', '');

            if(!Verify::verificaPayPassword($accountId, $request->input('operating_pw'))){
                return $this->presenter->json([], "交易密码错误", 2);
            }
        }else{
            if($request->input('operating_pw') != config('admin.operating_pw')){
                return $this->presenter->json([], "操作密码错误", 2);
            }
        }
        $id = $request->input('id');
        $data = DB::table('bank_card_info')
                ->where('id', $id)
                ->get();
        $data = json_decode(json_encode($data),true);
        $data[0]['login_pwd'] = Functions::desing3des($data[0]['login_pwd']);
        $data[0]['ukey_pwd'] = Functions::desing3des($data[0]['ukey_pwd']);
        $data[0]['pay_pwd'] = Functions::desing3des($data[0]['pay_pwd']);
        return $this->presenter->json($data[0], "成功", 1);
    }
    */
}
