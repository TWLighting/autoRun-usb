<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;

class DevicesTestController extends Controller
{

    public function addJob(Request $request)
    {
        $this->validate($request, [
            'hashcode' => 'required',
            'usb_uid' => 'required',
            'autorun_job_id' => 'required|integer',
        ]);

        $autorun_job_id = $request->input('autorun_job_id');
        if ($autorun_job_id >= 0) {
            return $this->presenter->json([], "测试接口autorun_job_id 务必小于0", 2);
        }
        $data = [
            'autorun_job_id' => $autorun_job_id,
            'usb_uid' => $request->input('usb_uid'),
            'usb_device_hashcode' => $request->input('hashcode'),
            'action' => $request->input('action', 1),
            'callback_info' => $request->input('callback_info', null),
            'status' => 0,
        ];
        $counts = abs($autorun_job_id);
        $insertData = [];
        if ($counts > 500) {
            $counts = 500;
        }

        for ($i = 1; $i <= $counts; $i++) {
            $insertData[] = $data;
            if (count($insertData) >= 100) {
                DB::table('usb_job')->insert($insertData);
                $insertData = [];
            }
        }

        if ($insertData) {
            DB::table('usb_job')->insert($insertData);
        }

        return $this->presenter->json([], "新增完成", 1);
    }

    public function testResult(Request $request)
    {
        $this->validate($request, [
            'hashcode' => 'required',
            'notreport_sec' => 'integer|min:1|max:300'
        ]);

        $hashcode = $request->input('hashcode');
        $s = intval($request->input('notreport_sec', 30));

        $first = DB::table('usb_job')
            ->where('usb_device_hashcode', $hashcode)
            ->orderBy('created_at', 'DESC')
            ->first();

        if (!$first) {
            return $this->presenter->json([], "成功", 1);
        }

        $callback_info = $first->callback_info;
        $data = DB::table('usb_job')
            ->selectRaw('usb_uid, action')
            ->selectRaw("SUM( CASE WHEN `status` = '0' THEN 1 ELSE 0 END ) AS wait_total")
            ->selectRaw("SUM( CASE WHEN `status` = '1' THEN 1 ELSE 0 END ) AS success_total")
            ->selectRaw("SUM( CASE WHEN `status` = '2' THEN 1 ELSE 0 END ) AS failed_total")
            ->selectRaw("SUM( CASE WHEN `status` = '3' THEN 1 ELSE 0 END ) AS timeout_total")
            ->selectRaw("SUM( CASE WHEN `status` = '4' and updated_at >= (NOW() - INTERVAL " . $s . " SECOND) THEN 1 ELSE 0 END ) AS processing_total")
            ->selectRaw("SUM( CASE WHEN `status` = '4' and updated_at < (NOW() - INTERVAL ". $s ." SECOND) THEN 1 ELSE 0 END ) AS notreport_total")
            ->where('usb_device_hashcode', $hashcode)
            ->where('callback_info', $callback_info)
            ->where('autorun_job_id', '<', 0)
            ->groupBy('usb_uid', 'action')
            ->orderBy('usb_uid', 'ASC')
            ->orderBy('action', 'ASC');

        if ($request->filled('autorun_job_id')) {
            $data->where('autorun_job_id', $request->input('autorun_job_id'));
        }
        $data = $data->get();
        $result = [];
        foreach($data as $val) {
            $uid = $val->usb_uid;
            $action = $this->convertJobAction($val->action);
            if (!isset($result[$uid])) {
                $result[$uid] = [];
            }
            if (!isset($result[$uid][$action])) {
                $result[$uid][$action] = [];
            }

            $result[$uid][$action]['wait'] = intval($val->wait_total);
            $result[$uid][$action]['success'] = intval($val->success_total);
            $result[$uid][$action]['failed'] = intval($val->failed_total);
            $result[$uid][$action]['timeout'] = intval($val->timeout_total);
            $result[$uid][$action]['processing'] = intval($val->processing_total);
            $result[$uid][$action]['notreport'] = intval($val->notreport_total);
        }

        return $this->presenter->json($result, "成功", 1);
    }

    private function convertJobAction($action)
    {
        $return = $action;
        switch ($action)
        {
            case 0:
                // $return = "按压";
                $return = "usbcontrol";
                break;
            case 1:
                // $return = "重開電";
                $return = "portpowerrestart";
                break;
            case 2:
                // $return = "分享";
                $return = "shareusb";
                break;
            case 3:
                // $return = "取消分享";
                $return = "unshareusb";
                break;
            case 4:
                // $return = "取得設備清單";
                $return = "getusbinfo";
                break;
            default:
                $return = $action;
        }
        return $return;
    }
}
