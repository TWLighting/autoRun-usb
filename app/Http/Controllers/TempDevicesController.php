<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DB;
use Log;

class TempDevicesController extends Controller
{
    /**
     * 设备注册
     *
     */
    public function register(Request $request)
    {
        Log::channel('devices')->info('[register] 接收资料: ' . json_encode($request->input(), 320));
        $this->validate($request, [
            'hashcode' => 'required',
        ]);

        $hashcode = $request->input('hashcode');
        $isQuery = boolval($request->input('type', '') == 'query');

        $usb_device = DB::table('usb_device')
            ->select(['id'])
            ->where('hashcode', $hashcode)
            ->first();
        if ($usb_device) {
            return $this->presenter->json([], "设备已存在", 2);
        }


        $result = DB::transaction(function () use ($hashcode, $isQuery) {
            $return = [
                'status' => 2,
                'msg' => '序号异常，不能烧录',
            ];
            $data = DB::table('temp_device')
                ->where('hashcode', $hashcode)
                ->where('status', 0)
                ->limit(1)
                ->lockForUpdate()
                ->first();

            if ($data) {
                if ($data->burn_status != 0) {
                    $return['msg'] = '序号已登录过，不能烧录';
                } elseif ($data->print_status != 1) {
                    $return['msg'] = '序号未列印，不能烧录';
                } else {
                    // burn_status = 0 and print_status = 1
                    $return = [
                        'status' => 1,
                        'msg' => '序号正常，可以烧录',
                    ];
                    if (!$isQuery) {
                        $updated = DB::table('temp_device')
                            ->where('id', $data->id)
                            ->where('burn_status', 0)
                            ->update(['burn_status' => 1]);
                    }
                }
            }
            return $return;
        });

        return $this->presenter->json([], $result['msg'], $result['status']);

    }

    /**
     * 设备回报测试状态
     *
     */
    public function updateTestStatus(Request $request)
    {
        $this->validate($request, [
            'hashcode' => 'required',
            'test_status' => 'required',
        ]);

        $hashcode = $request->input('hashcode');
        $exist = DB::table('temp_device')->where('hashcode', $hashcode)->exists();
        if (!$exist) {
            return $this->presenter->json([], "更新失败，hashcode不存在", 2);
        }
        $updated = DB::table('temp_device')
            ->where('hashcode', $hashcode)
            ->update(['test_status' => intval($request->input('test_status'))]);

        /*
        if (!$updated) {
            return $this->presenter->json([], "更新失败", 2);
        }*/

        return $this->presenter->json([], "更新成功", 1);
    }

    public function testDeviceList(Request $request)
    {
        $this->validate($request, [
            'password' => 'required',
        ]);

        if ($request->input('password') != 'CkJ2iZprN2') {
            return $this->presenter->json([], "资料异常", 2);
        }

        $data = DB::table('temp_device')
            ->select(['id', 'hashcode'])
            ->where('status', 0)
            ->where('print_status', 1)
            ->where('burn_status', 1)
            ->where('test_status', '>=', 1)
            ->where('test_status', '<', 5)
            ->get();

        foreach ($data as $val) {
            $val->sequence = str_pad($val->id, 8, "0", STR_PAD_LEFT);
        }

        return $this->presenter->json($data, "成功", 1);
    }
}