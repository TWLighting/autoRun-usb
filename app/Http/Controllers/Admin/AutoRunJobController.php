<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Libraries\Functions;
use App\Libraries\Verify;
use Illuminate\Validation\ValidationException;
use Validator;
use DB;

class AutoRunJobController extends AdminController
{
    public function getList(Request $request)
    {

        $data = $this->getJobModel($request)
            ->select(['a.account', 'aj.*', 'bc.acc_name'])
            ->paginate($request->input('perpage', 20));

        $data->getCollection()->transform(function ($value) {
            $value->amount = round($value->amount, 2);
            return $value;
        });

        return $this->presenter->json($data, "成功", 1);
    }

    public function getListSum(Request $request)
    {
        $data = $this->getJobModel($request)
            ->selectRaw('SUM(aj.amount) as sum_amount')
            ->first();

        $data->sum_amount = round($data->sum_amount, 2);

        return $this->presenter->json($data, "成功", 1);
    }

    public function addJob(Request $request)
    {
        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }

        $this->validate($request, [
            'pay_password' => 'required'
        ]);

        $pay_password = $request->input('pay_password');
        $accountId = $request->session()->get('accountId', '');
        $account = $request->session()->get('account', '');
        $addJobModel = [];

        if(!Verify::verificaPayPassword($accountId, $pay_password)){
            return $this->presenter->json([], "交易密码错误", 2);
        }

        $data = $request->input('data');
        $newData = $data;
        if(!$newData){
            $newData = [0 => $request->input()];
        }

        $validator = Validator::make($newData, [
            '*.id' => 'required|numeric',
            '*.bankcard' => 'required|numeric',
            '*.amount' => 'required|numeric',
            '*.type' => 'required|numeric',
            '*.tran_card_name' => 'required_if:*.type,2',
            '*.tran_card_no' => 'required_if:*.type,2|numeric',
        ]);

        if(!empty($validator->errors()->all())){
            throw new ValidationException($validator);
        }

        foreach ($newData as $k => $v){
            $addJobModel[$k] = $this->addJobModel($v, $request->session()->get('topAccountId'), $account);
        }

        if(!$data){
            return $this->presenter->json([], $addJobModel[0]['msg'], $addJobModel[0]['msg_code']);
        }
        return $this->presenter->json($addJobModel, "成功", 1);
    }

    public function changeStatus(Request $request)
    {
        $this->validate($request, [
            'id' => 'required',
            'status' => 'required',
            'pay_password' => 'required'
        ]);
        $pay_password = $request->input('pay_password');
        $accountId = $request->session()->get('accountId', '');

        if (!Functions::isAdmin($request)){
            if(!Verify::verificaPayPassword($accountId, $pay_password)){
                return $this->presenter->json([], "交易密码错误", 2);
            }
        } else {
            if($pay_password != config('admin.operating_pw')){
                return $this->presenter->json([], "操作密码错误", 2);
            }
        }

        $id = $request->input('id');
        $status = $request->input('status');

        $data = DB::table('autorun_job')->where('id', $id);

        if (!Functions::isAdmin($request)) {
            $data->where('account_id', $request->session()->get('topAccountId'));
        }
        $data->update(['status' => $status]);

        return $this->presenter->json([], "成功", 1);
    }

    public function redoJobs(Request $request)
    {
        $this->validate($request, [
            'id_list' => 'required|array',
            'pay_password' => 'required'
        ]);
        $pay_password = $request->input('pay_password');
        $accountId = $request->session()->get('accountId', '');
        // 检查密码
        if (!Functions::isAdmin($request)){
            if(!Verify::verificaPayPassword($accountId, $pay_password)){
                return $this->presenter->json([], "交易密码错误", 2);
            }
        } else {
            if($pay_password != config('admin.operating_pw')){
                return $this->presenter->json([], "操作密码错误", 2);
            }
        }

        $redo = DB::table('autorun_job')
            ->whereIn('id', $request->input('id_list'))
            ->whereIn('status', [2]);

        if (!Functions::isAdmin($request)) {
            $redo->where('account_id', $request->session()->get('topAccountId'));
        }
        $redo->update(['status' => 0]);
        return $this->presenter->json([], "成功", 1);
    }

    private function getJobModel($request)
    {
        $data = DB::table('autorun_job AS aj')
            ->join('account AS a', 'aj.account_id', '=', 'a.id')
            ->join('bank_card_info AS bc', 'aj.card_no', '=', 'bc.card_no')
            ->orderBy('aj.id', 'desc');

        if (!Functions::isAdmin($request)) {
            $data->where('aj.account_id', $request->session()->get('topAccountId'));
        } elseif ($request->filled('account')) {
            $data->where('a.account', $request->input('account'));
        }


        if ($request->filled('bank_name')) {
            $data->where('aj.bank_name', 'like', '%' . $request->input('bank_name') . '%');
        }

        if ($request->filled('dev_id')) {
            $data->where('aj.dev_id', $request->input('dev_id'));
        }

        if ($request->filled('bankcard')) {
            $data->where('aj.card_no', 'like', '%' . $request->input('bankcard') . '%');
        }

        $status = $request->input('status', '');
        if($status != ""){
            $data->where('aj.status', '=', $status);
        }

        if($request->filled('startDate')){
            $data->where('aj.created_at', '>=', $request->input('startDate'));
        }
        if($request->filled('endDate')){
            $data->where('aj.created_at', '<=', $request->input('endDate'));
        }

        if ($request->filled('job_order_number')) {
            $data->where('aj.job_order_number', 'like', '%' . $request->input('job_order_number') . '%');
        }

        return $data;
    }

    private function addJobModel($request, $accountId, $account)
    {
        $id = $request['id'];
        $card_no = $request['bankcard'];
        $amount = $request['amount'];
        $tran_card_name = $request['tran_card_name'] ? trim($request['tran_card_name']) : "";
        $tran_card_no = $request['tran_card_no'] ? trim($request['tran_card_no']) : "";
        $user_attach = $request['attach'] ?? "";
        $type = $request['type'];
        $mode = empty($request['mode']) ? 0 : $request['mode'];

        if($card_no == $tran_card_no){
            return array(
                'msg' => '转账卡号相同',
                'msg_code' => 2,
            );
        }

        if($amount < 0.01){
            return array(
                'id' => $id,
                'msg' => '金额需大于0.01',
                'msg_code' => 2,
            );
        }
        $UsbKeyObject = array();


        $bankCardInfoObject = DB::table('bank_card_info')
            ->where('status', '1')
            ->where('card_no', $card_no)
            ->where('account_id', $accountId);
            if($mode != 1){
                $bankCardInfoObject->whereNotNull('usb_key_id');
            }
        $bankCardInfoObject = $bankCardInfoObject->first();
        if(empty($bankCardInfoObject)){
            return array(
                'id' => $id,
                'msg' => '银行卡搜索错误',
                'msg_code' => 2,
            );
        }
        if($mode != 1){
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

            if(empty($UsbKeyObject)){
                return array(
                    'id' => $id,
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
        $insertId = $autorun_job->insertGetId($insertList);

        $autorun_job
            ->where('id', $insertId)
            ->update(['job_order_number' => time().$id]);

        return array(
            'id' => $id,
            'msg' => '成功',
            'msg_code' => 1,
        );
    }

}
