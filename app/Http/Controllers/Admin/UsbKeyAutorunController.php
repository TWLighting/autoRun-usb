<?php

namespace App\Http\Controllers\Admin;

use App\Libraries\Functions;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;
use Log;

class UsbKeyAutorunController extends AdminController
{
    public function updateUKeyAutoRun(Request $request)
    {
        $this->validate($request, [
            'usb_uid' => 'required',
            'rundevice_id' => 'present'
        ]);

        $usb_uid = $request->input('usb_uid');
        $rundevice_id = $request->input('rundevice_id', 0);
        $time = Carbon::now();

        if ($rundevice_id) {
            $autorun_device = DB::table('autorun_device AS ad')
                ->select(['dev_id'])
                ->where('id', $rundevice_id)
                ->first();

            if (!$autorun_device) {
                return $this->presenter->json([], '系统异常:autorun_device不存在', 2);
            }
        } else {
            $rundevice_id = 0;
            $autorun_device = collect();
            $autorun_device->dev_id = '无配置';
        }

        $usb_key = DB::table('usb_key AS uk')
            ->select(['uk.*', 'ud.hashcode'])
            ->join('usb_port AS up', 'up.usb_uid', '=', 'uk.usb_uid')
            ->join('usb_device AS ud', 'up.usb_device_id', '=', 'ud.id')
            ->where('uk.usb_uid', $usb_uid)
            ->first();

        if (!$usb_key) {
            return $this->presenter->json([], '系统异常:usb_uid没绑定U盾', 2);
        }

        if ($usb_key->autorun_id == $rundevice_id) {
            return $this->presenter->json([], 'Device无更新', 2);
        }

        $msg = sprintf('%s修改autorun为%s, usb-uid:%s', $request->session()->get('account'), $rundevice_id, $usb_uid);
        Log::channel('admin_autorun')->info($msg);
        DB::table('usb_key')
            ->where('id', $usb_key->id)
            ->update([
                'autorun_id' => $rundevice_id,
                'autorun_change_time' => $time
            ]);

        // 更新订单 autorun_job dev_id
        DB::table('autorun_job')
            ->where('usb_uid', $usb_key->usb_uid)
            ->where('usb_device_hashcode', $usb_key->hashcode)
            ->where(function ($query) {
                $query->where('status', 0)
                    ->orWhere('status', 2);
            })
            ->update([
                'dev_id' => $autorun_device->dev_id,
                'autorun_change_time' => $time
            ]);

        return $this->presenter->json([], '更新成功', 1);
    }


    public function getUKeyByAutorun(Request $request)
    {
        $this->validate($request, [
            'autorun_id' => 'required'
        ]);

        $data = DB::table('usb_key AS uk')
            ->select(['a.account', 'uk.name', 'uk.usb_uid', 'up.index', 'ud.hashcode'])
            ->join('usb_port AS up', 'up.usb_uid', '=', 'uk.usb_uid')
            ->join('usb_device AS ud', 'up.usb_device_id', '=', 'ud.id')
            ->join('account AS a', 'ud.account_id', '=', 'a.id')
            ->where('uk.autorun_id', $request->input('autorun_id'))
            ->orderBy('ud.account_id', 'ASC')
            ->orderBy('ud.id', 'ASC')
            ->orderBy('up.index', 'ASC');

        if ($request->filled('account')) {
            $data->where('a.account', $request->input('account'));
        }

        $data = $data->paginate($request->input('perpage', 20));

        return $this->presenter->json($data, "成功", 1);
    }
}