<?php

namespace App\Http\Controllers;

use App\Libraries\Functions;
use Illuminate\Http\Request;
use DB;
use Log;

class UBoxController extends Controller
{

    public function register(Request $request)
    {
        $this->validate($request, [
            'usb_uid' => 'required',
        ]);

        $isQuery = boolval($request->input('type', '') == 'query');

        Log::channel('ubox')->info('[register]接收资料: ' . json_encode($request->input(), 320));
        $usb_uid = $request->input('usb_uid');

        if (!Functions::verifyUidCode($usb_uid)) {
            return $this->presenter->json([], '序号验证异常，不能烧录', 2);
        }

        $result = DB::transaction(function () use ($usb_uid, $isQuery) {
            $return = [
                'status' => 2,
                'msg' => '序号异常，不能烧录',
            ];
            $data = DB::table('temp_ubox')
                ->where('usb_uid', $usb_uid)
                ->limit(1)
                ->lockForUpdate()
                ->first();

            if ($data) {
                if ($data->burn_status == -1) {
                    $return['status'] = 3;
                    $return['msg'] = '序号已作废';
                } elseif ($data->burn_status != 0) {
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
                        $updated = DB::table('temp_ubox')
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

    public function discard(Request $request)
    {
        $this->validate($request, [
            'usb_uid' => 'required',
        ]);

        $updated = DB::table('temp_ubox')
            ->where('usb_uid', $request->input('usb_uid'))
            ->update(['burn_status' => -1]);

        /*
        if (!$updated) {
            return $this->presenter->json([], '作废失败', 2);
        }
        */
        return $this->presenter->json([], '作废成功', 1);
    }

}
