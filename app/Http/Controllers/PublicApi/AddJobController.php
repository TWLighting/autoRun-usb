<?php

namespace App\Http\Controllers\PublicApi;

use Illuminate\Http\Request;
use DB;
use Log;
use App\Libraries\Cryptology;
use Validator;
use Illuminate\Validation\ValidationException;


class AddJobController extends GlobalController
{

    public function addJob(Request $request)
    {
        $addJobModel = [];
        $this->validate($request, [
            'account' => 'required',
            'data' => 'required',
            'sign' => 'required'
        ]);

        $data = $request->input('data');
        $account = $request->input('account');
        $accountList = DB::table('account')->where('account', $account)->first();
        if(!$accountList){
            return $this->presenter->json([], "无此账号", 2);
        }

        $result = openssl_decrypt($data, 'des-ede3', $accountList->des_key, 0);
        $addJobList = json_decode($result, true);
        $sign = Cryptology::md5sign($accountList->md5_key, array('data' => $result));
        Log::channel('autorunJob')->info('[addJob] 接收资料: ' . $result);

        if ($sign != $request->input('sign')) {
            return $this->presenter->json([], "验签失败", 2);
        }

        $validator = Validator::make($addJobList, [
            '*.bankcard' => 'required|numeric',
            '*.amount' => 'required|numeric',
            '*.type' => 'required|numeric',
            '*.tran_card_name' => 'required_if:*.type,2',
            '*.tran_card_no' => 'required_if:*.type,2|numeric',
        ]);

        if (!empty($validator->errors()->all())) {
            throw new ValidationException($validator);
        }

        foreach ($addJobList as $k => $v) {
            $addJobModel[$k] = $this->addJobModel($v, $accountList->top_account_id, $account);
        }

        if (!$data) {
            return $this->presenter->json([], $addJobModel[0]['msg'], $addJobModel[0]['msg_code']);
        }
        return $this->presenter->json($addJobModel, "成功", 1);
    }

    private function addJobModel($request, $accountId, $account)
    {
        $card_no = $request['bankcard'];
        $amount = $request['amount'];
        $tran_card_name = $request['tran_card_name'] ? trim($request['tran_card_name']) : "";
        $tran_card_no = $request['tran_card_no'] ? trim($request['tran_card_no']) : "";
        $user_attach = $request['attach'] ?? "";
        $type = $request['type'];


        if($card_no == $tran_card_no){
            return array(
                'msg' => '转账卡号相同',
                'msg_code' => 2,
            );
        }

        if($amount < 0.01){
            return array(
                'msg' => '金额需大于0.01',
                'msg_code' => 2,
            );
        }



        $bankCardInfoObject = DB::table('bank_card_info')
            ->where('status', '1')
            ->where('account_id', $accountId)
            ->where('card_no', $card_no);
        if($request['mode'] != 1){
            $bankCardInfoObject->whereNotNull('usb_key_id');
        }
        $bankCardInfoObject = $bankCardInfoObject->first();
        if (empty($bankCardInfoObject)) {
            return array(
                'msg' => '银行卡搜索错误',
                'msg_code' => 2,
            );
        }

        if($request['mode'] != 1) {
            $UsbKeyObject = DB::table('usb_key AS uk')
                ->select([
                    'ad.dev_id', 'ud.hashcode', 'ud.ip', 'uk.usb_uid', 'uk.autorun_change_time',
                ])
                ->join('autorun_device AS ad', 'uk.autorun_id', '=', 'ad.id')
                ->join('usb_port AS up', 'uk.usb_uid', '=', 'up.usb_uid')
                ->join('usb_device AS ud', 'up.usb_device_id', '=', 'ud.id')
                ->where('uk.id', $bankCardInfoObject->usb_key_id)
                ->where('up.enable', '1')
                ->where('ud.enable', '1')
                ->where('ud.ip', '!=', '0')
                ->where('ud.account_id', $accountId)
                ->whereNotNull('uk.usb_uid')
                ->first();

            if (empty($UsbKeyObject)) {
                return array(
                    'msg' => '设备脚位搜索错误',
                    'msg_code' => 2,
                );
            }
        }

        if($request['mode'] != 1){
            $mode = 0;
            $dev_id = $UsbKeyObject->dev_id;
            $usb_uid = $UsbKeyObject->usb_uid;
            $usb_device_hashcode = $UsbKeyObject->hashcode;
            $autorun_change_time = $UsbKeyObject->autorun_change_time;
        }else{
            $mode = 1;
            $dev_id = "manual_device";
            $usb_uid = null;
            $usb_device_hashcode = $account;
            $autorun_change_time = null;
        }

        $insertList = [
            'account_id' => $accountId,
            'bank_name' => $bankCardInfoObject->bank_name,
            'card_no' => $card_no,
            'amount' => $amount,
            'dev_id' => $dev_id,
            'usb_uid' => $usb_uid,
            'usb_device_hashcode' => $usb_device_hashcode,
            'type' => $type,
            'manual_mode' => $mode,
            'tran_card_name' => $tran_card_name,
            'tran_card_no' => $tran_card_no,
            'user_attach' => $user_attach,
            'autorun_change_time' => $autorun_change_time,
        ];
        $autorun_job = DB::table('autorun_job');
        $id = $autorun_job->insertGetId($insertList);
        $job_order_number = time() . $id;
        $autorun_job
            ->where('id', $id)
            ->update(['job_order_number' => $job_order_number]);

        return array(
            'msg' => '成功',
            'msg_code' => 1,
            'job_order_number' => $job_order_number,
        );
    }

}
