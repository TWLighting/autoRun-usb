<?php

namespace App\Http\Controllers\Admin;

use App\Libraries\Functions;
use Illuminate\Http\Request;
use Carbon\Carbon;
use DB;
use Log;

class UsbPortController extends AdminController
{
    public function getUserPort(Request $request)
    {
        $this->validate($request, [
            'device' => 'required'
        ]);

        $data = DB::table('usb_device as dev')
            ->select([
                'dev.*', 'port.*', 'port.id as port_id',
                'key.name AS usb_key_name', 'key.id AS usb_key_id', 'key.autorun_id AS usb_autorun_id'
            ])
            ->Join('usb_port as port', 'dev.id', '=', 'port.usb_device_id')
            ->leftJoin('usb_key as key', 'port.usb_uid', '=', 'key.usb_uid')
            ->orderBy('port.index', 'ASC');

        if (!Functions::isAdmin($request)) {
            $data->where(['dev.account_id' => $request->session()->get('topAccountId')]);
        }
        $data->where(['dev.hashcode' => $request->input('device')]);
        $result = $data->get();
        if (!$result->count()) {
            return response('Unauthorized.', 401);
        }

        return $this->presenter->json($result, "成功", 1);
    }
}