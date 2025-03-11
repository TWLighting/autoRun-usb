<?php

namespace App\Http\Controllers;

use App\Libraries\Functions;
use App\Events\NotificationEvent;
use App\Libraries\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use DB;
use Event;
use Log;

class AutoJobController extends Controller
{

    public function getJob(Request $request)
    {
        $this->validate($request, [
            'dev_id' => 'required',
        ]);
        Log::channel('obtpjob')->info('[getJob]接收资料: ' . json_encode($request->input(), 320));

        $dev_id = $request->input('dev_id');
        $data = null;

        DB::transaction(function () use (&$data, $dev_id) {
            $data = DB::table('autorun_job')
                ->where('status', '0')
                ->where('dev_id', $dev_id)
                ->where(function ($query) {
                    $query->whereNull('autorun_change_time')
                          ->orWhereRaw('NOW() > DATE_ADD(`autorun_change_time`,INTERVAL 1 MINUTE)');
                })
                ->orderBy('id', 'asc')
                ->limit(1)
                ->lockForUpdate()
                ->first();

            if ($data) {
                $updated = DB::table('autorun_job')
                    ->where('id', $data->id)
                    ->where('status', 0)
                    ->update(['status' => '3']);
            }
        });

        if (empty($data)) {
            return $this->presenter->json([], "無资料", 2);
        }

        $bankCardInfoObject = DB::table('bank_card_info AS bci')
            ->select([
                'bci.login_account',
                'bci.login_pwd', 'bci.ukey_pwd', 'bci.pay_pwd',
                'bcc.value AS cookie_value'
            ])
            ->where('bci.status', '1')
            ->where('bci.card_no', $data->card_no)
            ->where('bci.account_id', $data->account_id)
            ->leftJoin('bank_card_cookie AS bcc', 'bci.card_no', '=', 'bcc.card_no');

        // 自动模式增加检查
        if ($data->manual_mode != '1') {
            $bankCardInfoObject->whereNotNull('bci.usb_key_id');
        }

        $bankCardInfoObject = $bankCardInfoObject->first();
        if (!$bankCardInfoObject) {
            Log::channel('obtpjob')->info(sprintf("[getJob]失败，卡号异常 [%s]", $data->card_no));
            $msg = '卡号不存在或已停用';
            $updated = DB::table('autorun_job')
                    ->where('id', $data->id)
                    ->update(['status' => '-1', 'attach' => $msg]);
            return $this->presenter->json([], $msg, 2);
        }

        Log::channel('obtpjob')->info(sprintf("[getJob]成功，回传 id [%s]", $data->id));
        $return = [
            "ID" => $data->id,
            "Url" => $data->recharge_url,
            "BankCode" => Functions::convertBank($data->bank_name),
            "BankName" => $data->bank_name,
            "CardNo" => $data->card_no,
            "LoginAccount" => $bankCardInfoObject->login_account,
            "LoginPWD" => $bankCardInfoObject->login_pwd,
            "UKeyPWD" => $bankCardInfoObject->ukey_pwd,
            "PayPWD" => $bankCardInfoObject->pay_pwd,
            "Hashcode" => $data->usb_device_hashcode,
            "UsbUid" => $data->usb_uid,
            "Amount" =>  round($data->amount, 2),
            "ToCardName" => $data->tran_card_name,
            "Type" => $data->type,
            "ToCardNo" => $data->tran_card_no,
            "UserAttach" => $data->user_attach,
            "Version" => config('autorun.version'),
            "cookie_value" => $bankCardInfoObject->cookie_value,
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
        $attach = $request->input('attach', '');
        $status = $request->input('status');

        if (mb_strlen($attach) > 200) {
            $attach = "备注信息过长，请联络系统管理员。";
        }
        Log::channel('obtpjob')->info('[update] 接收资料: ' . json_encode($request->input(), 320));

        $updated = DB::table('autorun_job')
            ->where('id',  $id)
            ->where('status', '3')
            ->update([
                'status' => $status,
                'attach' => $attach,
                'success_at' => Carbon::now()
            ]);
        if (!$updated) {
            return $this->presenter->json([], "失败", 2);
        }

        if ($status == 2) {
            $job = DB::table('autorun_job AS aj')
                ->select([
                    'a.account', 'a.telegram_path', 'a.telegram_chatid_trans',
                    'aj.id', 'aj.job_order_number', 'aj.usb_device_hashcode', 'aj.usb_uid'
                ])
                ->join('account AS a', 'a.id', '=', 'aj.account_id')
                ->where('aj.id', $id)
                ->first();
            if ($job->account && $job->job_order_number) {
                $msg = sprintf('交易失败，订单号:%s，备注:%s', $job->job_order_number, $attach);
                Log::channel('notify')->info($job->account . '接收通知: ' . $msg);
                Event::fire(new NotificationEvent($msg, $job->account));
                if ($job->telegram_path && $job->telegram_chatid_trans) {
                    NotificationHelper::telegramSendmsg($msg, $job->telegram_path, $job->telegram_chatid_trans);
                }
            }

            // 若有 延缓 参数，修改此设备这个port 的所有未执行任务
            if ($job && $request->filled('defer')) {
                $defer = intval($request->input('defer'));
                if ($defer) {
                    DB::table('autorun_job')
                        ->where('usb_device_hashcode', $job->usb_device_hashcode)
                        ->where('usb_uid', $job->usb_uid)
                        ->where('status', 0)
                        ->update([
                            'autorun_change_time' => Carbon::now()->addSeconds($defer-60),
                        ]);
                }
            }
        }

        Log::channel('obtpjob')->info(sprintf("[update] 更新状态成功 id [%s]", $id));
        return $this->presenter->json([], "成功", 1);
    }
}
