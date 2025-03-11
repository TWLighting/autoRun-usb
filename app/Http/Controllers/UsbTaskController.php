<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use DB;
use Log;

class UsbTaskController extends Controller
{

    public function addJob(Request $request)
    {
        $timeout = 30;
        set_time_limit($timeout + 10);

        Log::channel('usbtask')->info('[addJob] 接收资料: ' . json_encode($request->input(), 320));
        $this->validate($request, [
            'hashcode' => 'required',
            'usb_uid' => 'required',
        ]);

        $autorun_job_id = intval($request->input('autorun_job_id', null));
        if (!$autorun_job_id) {
            $autorun_job_id = null;
        } elseif ($autorun_job_id < 0) {
            return $this->presenter->json([], "autorun_job_id不能为负数", 2);
        }
        $id = DB::table('usb_job')->insertGetId([
            'autorun_job_id' => $autorun_job_id,
            'usb_uid' => $request->input('usb_uid'),
            'usb_device_hashcode' => $request->input('hashcode'),
            'action' => $request->input('action', 1),
            'callback_info' => $request->input('callback_info', null),
            'status' => 0,
        ]);
        for ($i = 0; $i < $timeout; $i++) {
            sleep(1);
            $data = DB::table('usb_job')
                ->whereIn('status', [1, 2])
                ->where('id', $id)
                ->orderBy('id', 'asc')
                ->limit(1)
                ->first();
            if (!empty($data)) {
                if ($data->status == 1) {
                    return $this->presenter->json($data, "成功", 1);
                } else {
                    $msg = ($data->attach) ? $data->attach : '请联络系统管理员';
                    return $this->presenter->json([], $msg, 2);
                }
            }
        }
        DB::table('usb_job')
            ->whereIn('status', [0, 4])
            ->where('id', $id)
            ->update(['status' => 3]);
        return $this->presenter->json([], "超時", 2);
    }

    public function getTask(Request $request)
    {
        // Log::channel('usbtask')->info('[getTask] 接收资料: ' . json_encode($request->input(), 320));
        $this->validate($request, [
            'hashcode' => 'required',
        ]);
        $hashcode = $request->input('hashcode');
        $data = null;
        DB::transaction(function () use (&$data, $hashcode) {
            //DB::connection()->enableQueryLog();
            $data = DB::table('usb_job')
                ->where('status', '0')
                ->where('usb_device_hashcode', $hashcode)
                ->orderBy('id', 'asc')
                ->limit(1)
                ->lockForUpdate()
                ->first();

            /*
            $queries    = DB::getQueryLog();
            $last_query = end($queries);
            error_log(print_r($last_query, true));
            */
            if ($data) {
                $updated = DB::table('usb_job')
                    ->where('id', $data->id)
                    ->where('status', 0)
                    ->update(['status' => '4']);
            }
        });

        if (empty($data)) {
            return $this->presenter->json([], "無资料", 2);
        }

        $return = [
            "work_Id" => $data->id,
            "work_name" => $data->action,
            "usb_uid" => $data->usb_uid,
            "callback_info" => $data->callback_info,
        ];

        Log::channel('usbtask')->info(sprintf("[getTask] 成功 id [%s]", $data->id));
        return $this->presenter->json($return, "成功", 1);
    }

    public function updateTask(Request $request)
    {
        $this->validate($request, [
            'work_Id' => 'required|integer',
            'result' => [
                'required',
                Rule::in([1, 2]),
            ],
        ]);
        $id = $request->input('work_Id');

        Log::channel('usbtask')->info('[updateTask] 接收资料: ' . json_encode($request->input(), 320));
        $updated = DB::table('usb_job')
            ->where('id', $id)
            ->where('status', '4')
            ->update([
                'status' => $request->input('result'),
                'attach' => $request->input('remark'),
            ]);
        if (!$updated) {
            // 失败时 log 写进 attach
            DB::table('usb_job')
                ->where('id', $id)
                ->update([
                    'attach' => json_encode($request->input(), 320),
                ]);
            return $this->presenter->json([], "失败", 2);
        }
        Log::channel('usbtask')->info(sprintf("[updateTask] 更新状态成功 id [%s]", $id));
        return $this->presenter->json([], "成功", 1);
    }
}
