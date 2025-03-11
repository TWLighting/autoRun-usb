<?php

namespace App\Http\Controllers\Admin;

use App\Libraries\Functions;
use App\Libraries\NotificationHelper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use DB;

class UsbDeviceController extends AdminController
{
    public function getList(Request $request)
    {
        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }

        $data = DB::table('usb_device as ud')
            ->select(['a.account', 'a.top_account_id', 'ad.dev_id AS autorun_name', 'ud.*'])
            ->leftjoin('account AS a', 'ud.account_id', '=', 'a.id')
            ->leftJoin('autorun_device AS ad', 'ad.id', '=', 'ud.autorun_id')
            ->orderBy('ud.id', 'desc');
        if (!Functions::isAdmin($request)) {
            $data->where('ud.account_id', $request->session()->get('topAccountId'));
        }

        if ($request->filled('account')) {
            $data->where('a.account', 'like', '%' . $request->input('account') . '%');
        }
        if ($request->filled('hashcode')) {
            $data->where('ud.hashcode', 'like', '%' . $request->input('hashcode') . '%');
        }
        if ($request->filled('ip')) {
            $data->where('ud.ip', 'like', '%' . $request->input('ip'));
        }
        if ($request->filled('nickname')) {
            $data->where('ud.nickname', 'like', '%' . $request->input('nickname') . '%');
        }
        if ($request->filled('enable')) {
            $data->where('ud.enable', $request->input('enable'));
        }

        $result = $data->paginate($request->input('perpage', 20));

        // 读取最新版本号
        $config = DB::table('config')
            ->where('name', 'device_version')
            ->first();
        $device_version = ($config) ? $config->value : '';

        $device_version = collect(['device_version' => $device_version]);
        $result = $device_version->merge($result);

        return $this->presenter->json($result, "成功", 1);
    }

    public function getAll(Request $request)
    {
        // 空的port 子查询
        $empty_port = DB::table('usb_port')
            ->select('usb_device_id', DB::raw('count(*) as empty_port'))
            ->whereNotNull('usb_uid')
            ->whereNotIn('usb_uid', function ($query) use ($request) {
                $query->select('usb_uid')
                    ->from('usb_key')
                    ->whereNotNull('usb_uid')
                    ->where('account_id', $request->session()->get('topAccountId'));
            })
            ->groupBy('usb_device_id');

        //新增银行卡时，只能选择自己的设备
        $data = DB::table('usb_device as ud')
            ->selectRaw('ud.*, IFNULL(a.empty_port, 0) AS empty_port')
            ->leftJoinSub($empty_port, 'a', function ($join) {
                $join->on('ud.id', '=', 'a.usb_device_id');
            })
            ->where('ud.account_id', $request->session()->get('topAccountId'))
            ->where('ud.enable', 1)
            ->orderBy('created_at', 'DESC');

        $result = $data->get();

        return $this->presenter->json($result, "成功", 1);
    }

    public function create_device(Request $request)
    {
        $this->validate($request, [
            'hashcode' => 'required',
            'port_count' => 'required',
            'autorun_id' => 'required',
        ]);

        $accountId = null;
        if ($request->filled('account')) {
            $account = DB::table('account')
                ->where('account', '=', $request->input('account'))
                ->first();

            if (!$account) {
                return $this->presenter->json('', '商户号异常', 2);
            }
            $accountId = $account->id;
        }

        $hashcode = $request->input('hashcode');
        $port_count = $request->input('port_count');
        $autorun_id = $request->input('autorun_id');

        $exist_hashcode = DB::table('usb_device')
            ->where('hashcode', '=', $hashcode)
            ->first();
        if ($exist_hashcode) {
            return $this->presenter->json('', 'USB設備ID已存在', 2);
        }

        $last_id = DB::table('usb_device')
            ->insertGetId([
                'hashcode' => $hashcode,
                'nickname' => $hashcode,
                'account_id' => $accountId,
                'autorun_id' => $autorun_id,
                'port_count' => $port_count,
            ]);

        if (!$last_id) {
            return $this->presenter->json([], '新增失敗', 2);
        }
        // 如果有 temp_device 更新 temp_device
        DB::table('temp_device')
            ->where('hashcode', $hashcode)
            ->where('status', 0)
            ->update(['status' => 1]);

        // 新增 port 表
        $need_data = array(
            'usb_device_id' => $last_id,
            'times' => $port_count,
        );
        $port = $this->insert_port($need_data);
        if (!$port) {
            return $this->presenter->json('', '新增port失敗', 2);
        }

        return $this->presenter->json($last_id, '新增成功', 1);
    }

    public function changeStatus(Request $request)
    {
        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }

        $this->validate($request, [
            'id' => 'required',
            'enable' => [
                'required',
                Rule::in([0, 1]),
            ],
        ]);

        $id = $request->input('id');
        $enable = $request->input('enable');

        $query = DB::table('usb_device')
            ->where('id', $id);

        if (!Functions::isAdmin($request)) {
            $query->where('account_id', $request->session()->get('topAccountId'));
        }
        $query->update(['enable' => $enable]);

        return $this->presenter->json([], "成功", 1);
    }

    public function register_device(Request $request)
    {
        $this->validate($request, [
            'hashcode' => 'required'
        ]);
        $hashcode = $request->input('hashcode');
        $usb_device = DB::table('usb_device');
        $result = $usb_device
            ->where(['hashcode' => $hashcode])
            ->whereNull('account_id')
            ->first();

        if (!$result) {
            return $this->presenter->json([], "无此装置", 2);
        }
        DB::table('usb_device')
            ->where('hashcode', $hashcode)
            ->update(['account_id' => $request->session()->get('topAccountId')]);

        return $this->presenter->json([], "成功", 1);
    }


    public function update_nickname(Request $request)
    {
        $this->validate($request, [
            'deviceName' => 'required',
            'newNickName' => 'required',
            'autorun_id' => 'present'
        ]);

        $update_data = [
            'nickname' => $request->input('newNickName'),
            'autorun_id' => intval($request->input('autorun_id')),
        ];

        $sql = DB::table('usb_device')->where('hashcode', $request->input('deviceName'));
        if (!Functions::isAdmin($request)) {
            unset($update_data['autorun_id']);
            $sql->where('account_id', $request->session()->get('topAccountId'));
        }
        $result = $sql->update($update_data);

        if (!$result) {
            return $this->presenter->json([], '更新失敗', 2);
        }
        return $this->presenter->json([], '更新成功', 1);
    }

    /**
     * 通知商户 未更新的设备
     */
    public function notifyOldVersionDevice(Request $request)
    {
        $config = DB::table('config')
            ->where('name', 'device_version')
            ->first();
        if (!$config) {
            return $this->presenter->json([], 'DB未配置device_version', 2);
        }

        $currentVersion = $config->value;
        $account = DB::table('account')
            ->select(['telegram_path', 'telegram_chatid'])
            ->whereIn('id', function($query) use($currentVersion) {
                $query->selectRaw('DISTINCT account_id')
                    ->from('usb_device')
                    ->whereNotNull('version')
                    ->where('version', '!=', $currentVersion);
            })
            ->where('telegram_path', '!=', '')
            ->where('telegram_chatid', '!=', '')
            ->whereNotNull('telegram_path')
            ->whereNotNull('telegram_chatid')
            ->groupBy('telegram_path', 'telegram_chatid')
            ->get();

        $msg = sprintf('设备已经更新至最新版本:%s，请暂停新增交易，完成现有交易后请重启设备。', $currentVersion);
        foreach($account as $a) {
            NotificationHelper::telegramSendmsg($msg, $a->telegram_path, $a->telegram_chatid);
        }
        return $this->presenter->json([], '成功', 1);
    }

    private function insert_port($need_data)
    {
        $usb_port = DB::table('usb_port');
        $insert_data = [];
        for ($start = 1; $start <= $need_data['times']; $start++) {
            $column_array = [
                'usb_device_id' => $need_data['usb_device_id'],
                'index' => $start,
            ];
            $insert_data[] = $column_array;
        }
        $last_usb_port_id = $usb_port->insert($insert_data);
        if (!$last_usb_port_id) {
            return false;
        }
        return true;
    }
}
