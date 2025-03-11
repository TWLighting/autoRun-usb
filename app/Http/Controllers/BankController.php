<?php

namespace App\Http\Controllers;

use App\Libraries\Cryptology;
use App\Libraries\CurlRequest;
use App\Libraries\NotificationHelper;
use App\Libraries\Verify;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;
use Log;

class BankController extends Controller
{

    public function getList(Request $request)
    {
        $this->validate($request, [
            'dev_id' => 'required',
        ]);
        Log::channel('bank')->info('[getList] 接收资料: ' . json_encode($request->input(), 320));
        $dev_id = $request->input('dev_id');

        $data = DB::table('autorun_device AS ad')
            ->select([
                'ad.dev_id', 'ud.hashcode AS device-hashcode', 'ud.ip AS device-ip',
                'up.index AS port', 'up.usb_status AS port-usb_status', 'up.usb_uid AS usb_uid',
                'bci.login_account', 'bci.bank_name', 'bci.card_no', 'bci.acc_name',
                'bci.login_pwd', 'bci.ukey_pwd', 'bci.pay_pwd',
                'bcc.value AS cookie_value'
            ])
            ->join('usb_key AS uk', 'ad.id', '=', 'uk.autorun_id')
            ->join('usb_port AS up', 'uk.usb_uid', '=', 'up.usb_uid')
            ->join('usb_device AS ud', 'up.usb_device_id', '=', 'ud.id')
            ->join('bank_card_info AS bci', 'uk.id', '=', 'bci.usb_key_id')
            ->leftJoin('bank_card_cookie AS bcc', 'bci.card_no', '=', 'bcc.card_no')
            ->where('ad.enable', 1)
            ->where('ad.dev_id', $dev_id)
            ->where('ud.enable', 1)
            ->where('up.enable', 1)
            ->where('uk.key_status', 1)
            ->where('bci.status', 1)
            ->whereNotNull('bci.usb_key_id')
            ->whereNotNull('uk.usb_uid')
            ->orderBy('bci.id', 'ASC')
            ->get();

        return $this->presenter->json($data, "成功", 1);
    }

    public function getLoginList(Request $request)
    {
        $this->validate($request, [
            'login_server_name' => 'required',
        ]);

        $specialList = [
            '1EA8' => 'ming'
        ];

        Log::channel('bank')->info('[getLoginList] 接收资料: ' . json_encode($request->input(), 320));
        $name = $request->input('login_server_name');

        $data = DB::table('login_server AS ls')
            ->select([
                'ls.name AS login_server_name', 'ud.hashcode AS device-hashcode', 'ud.ip AS device-ip',
                'up.index AS port', 'up.usb_status AS port-usb_status', 'up.usb_uid AS usb_uid',
                'bci.login_account', 'bci.bank_name', 'bci.card_no', 'bci.acc_name',
                'bci.login_pwd', 'bci.ukey_pwd', 'bci.pay_pwd',
                'bcc.value AS cookie_value'
            ])
            ->join('bank_card_info AS bci', 'ls.id', '=', 'bci.login_server_id')
            ->join('usb_key AS uk', 'bci.usb_key_id', '=', 'uk.id')
            ->join('usb_port AS up', 'uk.usb_uid', '=', 'up.usb_uid')
            ->join('usb_device AS ud', 'up.usb_device_id', '=', 'ud.id')
            ->leftJoin('bank_card_cookie AS bcc', 'bci.card_no', '=', 'bcc.card_no');

        if($request->filled('vid')) {
            $data->where('up.vid', $request->input('vid'));
        } else {
            $specialListKeys = array_keys($specialList);
            $data->where('ls.name', $name)
                ->where(function ($query) use ($specialListKeys) {
                    $query->whereNull('up.vid')
                        ->orWhereNotIn('up.vid', $specialListKeys);
                });
        }

        $data = $data->where('ud.enable', 1)
            ->where('up.enable', 1)
            ->where('uk.key_status', 1)
            ->where('bci.status', 1)
            ->whereNotNull('bci.usb_key_id')
            ->whereNotNull('uk.usb_uid')
            ->orderBy('bci.id', 'ASC')
            ->get();

        Log::channel('bank')->info('[getLoginList] 回传资料: ' . json_encode($data, 320));
        return $this->presenter->json($data, "成功", 1);
    }

    public function getListByAccount(Request $request)
    {
        $this->validate($request, [
            'account' => 'required',
            'password' => 'required',
        ]);

        $account = $request->input('account');
        $password = $request->input('password');

        $accountObject = DB::table('account')
            ->where('status', '1')
            ->where('account', $account)
            ->first();

        if (!$accountObject) {
            return $this->presenter->json([], "无此账号", 2);
        }

        if (!password_verify($password, $accountObject->password)) {
            return $this->presenter->json([], "密码错误", 2);
        }

        $data = DB::table('bank_card_info')
            ->select(['card_no', 'acc_name'])
            ->where('account_id', $accountObject->id)
            ->where('status', 1)
            ->orderBy('id', 'ASC')
            ->get();

        return $this->presenter->json($data, "成功", 1);
    }


    public function setCardCookie(Request $request)
    {
        $this->validate($request, [
            'card_no' => 'required',
            'cookie_value' => 'present',
        ]);

        DB::table('bank_card_cookie')->updateOrInsert(
            ['card_no' => $request->input('card_no')],
            ['value' => $request->input('cookie_value')]
        );

        return $this->presenter->json([], "成功", 1);
    }

    // 工具取得登入Cookie，需要输入商户号的交易密码
    public function getCardCookie(Request $request)
    {
        $this->validate($request, [
            'card_no' => 'required',
            'pay_password' => 'required',
        ]);

        $data = DB::table('bank_card_cookie AS bcc')
            ->select(['bcc.card_no', 'bcc.value AS cookie_value', 'bci.bank_name', 'bci.account_id'])
            ->join('bank_card_info AS bci', 'bcc.card_no', '=', 'bci.card_no')
            ->where('bcc.card_no', $request->input('card_no'))
            ->first();

        if (!$data) {
            return $this->presenter->json("", "成功", 1);
        }

        if(!Verify::verificaPayPassword($data->account_id, $request->input('pay_password'))){
            return $this->presenter->json([], "交易密码错误", 2);
        }

        $data->web_url = $this->getWebsite($data->bank_name);
        unset($data->account_id);
        return $this->presenter->json($data, "成功", 1);
    }

    //
    public function getCardCookieByAutorun(Request $request)
    {
        $this->validate($request, [
            'card_no' => 'required',
            'password' => 'required',
        ]);

        if($request->input('password') != config('autorun.bank_cookie_password')){
            return $this->presenter->json([], "密码错误", 2);
        }

        $data = DB::table('bank_card_cookie AS bcc')
            ->select(['bcc.card_no', 'bcc.value AS cookie_value', 'bci.bank_name'])
            ->join('bank_card_info AS bci', 'bcc.card_no', '=', 'bci.card_no')
            ->where('bcc.card_no', $request->input('card_no'))
            ->first();

        if (!$data) {
            return $this->presenter->json("", "成功", 1);
        }

        $data->web_url = $this->getWebsite($data->bank_name);
        return $this->presenter->json($data, "成功", 1);
    }

    public function detailGet(Request $request)
    {
        $this->validate($request, [
            'card_no' => 'required'
        ]);
        Log::channel('bank')->info('[detailGet] ' . $request->ip() . ' 接收资料: ' . json_encode($request->input(), 320));

        $data = DB::table('transaction_info')
            ->where('card_no', $request->input('card_no'))
            ->orderBy('trans_time', 'desc')
            ->orderBy('bankapi_time', 'desc')
            ->limit(2)
            ->get();

        if (!$data) {
            return $this->presenter->json([], "成功", 1);
        }

        foreach ($data as $value) {
            $value->amt = round($value->amt, 2);
            $value->balance = round($value->balance, 2);
        }

        return $this->presenter->json($data, "成功", 1);
    }

    public function detailUpdate(Request $request)
    {
        $this->validate($request, [
            'dev_id' => 'required',
            'card_no' => 'required',
            'bank_name' => 'required',
            'tran_time' => 'required',
            'amt' => 'numeric',
            'balance' => 'numeric',
            'tran_info' => 'required',
            'tran_type' => 'required',
            'tran_way' => 'required',
            'note' => 'required',
        ]);
        Log::channel('bank')->info('[detailUpdate] ' . $request->ip() . ' 接收资料: ' . json_encode($request->input(), 320));

        // 检查 dev_id
        $autorun = DB::table('autorun_device AS ad')
            ->select(['ad.dev_id', 'bci.account_id', 'bci.notify_enable'])
            ->join('usb_key AS uk', 'ad.id', '=', 'uk.autorun_id')
            ->join('bank_card_info AS bci', 'uk.id', '=', 'bci.usb_key_id')
            ->where('ad.enable', 1)
            ->where('ad.dev_id', $request->input('dev_id'))
            ->where('bci.card_no', $request->input('card_no'))
            ->whereNotNull('uk.usb_uid')
            ->first();

        if (!$autorun) {
            return $this->presenter->json([], "失败，银行卡不存在", 2);
        }

        $data = $request->input();

        $transaction_id = DB::transaction(function () use ($data) {
            $card_no = $data['card_no'];
            $trans_time = strtotime($data['tran_time']);
            $balance = round($data['balance'], 2);
            $bankapi_time = Carbon::now();

            if (!$trans_time) {
                $trans_time = Carbon::now();
            } else {
                $trans_time = date("Y-m-d H:i:s", $trans_time);
            }

            $insertData = [
                'card_no' => $card_no,
                'bank_name' => $data['bank_name'],
                'tran_time' => $data['tran_time'],
                'trans_time' => $trans_time,
                'amt' => round($data['amt'], 2),
                'balance' => $balance,
                'tran_info' => $data['tran_info'],
                'tran_type' => $data['tran_type'],
                'tran_way' => $data['tran_way'],
                'note' => $data['note'],
                'bankapi_time' => $bankapi_time,
            ];

            $id = DB::table('transaction_info')->insertGetId($insertData);

            // 更新余额
            DB::table('bank_card_info')
                ->where('card_no', $card_no)
                ->update(['balance' => $balance]);
            return $id;
        });

        if ($transaction_id) {
            $callbackData = [
                'account_id' => $autorun->account_id,
                'transaction_info_id' => $transaction_id,
            ];
            $callbackData['sign'] = Cryptology::md5sign(config('admin.callback_key'), $callbackData);
            //打回调
            CurlRequest::curl(route('callback_request'), $callbackData, [], 'off', 1);
        }

        // 是转入非转出时 发送通知
        if ($data['amt'] > 0 && $autorun->notify_enable) {
            $accountObject = DB::table('account')
                ->where('id', $autorun->account_id)
                ->first();

            if ($accountObject) {
                if ($accountObject->telegram_path && $accountObject->telegram_chatid_trans) {
                    $amt = round($data['amt'], 2);
                    $msg = sprintf("银行卡[%s]，入款金额[%s]，日期[%s]", $data['card_no'], $amt, $data['tran_time']);
                    NotificationHelper::telegramSendmsg(
                        $msg,
                        $accountObject->telegram_path,
                        $accountObject->telegram_chatid_trans,
                        1
                    );
                }
            }
        }

        return $this->presenter->json([], "成功", 1);
    }

    private function getWebsite($bank_name)
    {
        $web_url = "";
        switch ($bank_name)
        {
            case "工商银行":
                $web_url = "https://mybank.icbc.com.cn/icbc/newperbank/perbank3/frame/frame_index.jsp";
                break;
            case "农业银行":
                $web_url = "https://perbank.abchina.com/EbankSite/index.do";
                break;
            case "民生银行":
                $web_url = "https://nper.cmbc.com.cn/pweb/clogin.do";
                break;
            case "中国建设银行":
                $web_url = "https://ibsbjstar.ccb.com.cn/CCBIS/";
                break;
            default:

        }
        return $web_url;
    }
}
