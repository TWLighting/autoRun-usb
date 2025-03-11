<?php

namespace App\Http\Controllers;

use App\Libraries\Functions;
use App\Libraries\NotificationHelper;
use Illuminate\Http\Request;
use DB;
use Log;
use Carbon\Carbon;

class DevicesController extends Controller
{

    public function status(Request $request)
    {
        Log::channel('devices')->info('[status] 接收资料: ' . json_encode($request->input(), 320));
        $this->validate($request, [
            'hashcode' => 'required',
            'usb_uid' => 'required'
        ]);
        $input_data = $request->input();

        $data = DB::table('usb_device as ud')
            ->select([
                'ud.hashcode', 'ud.ip', 'ud.heartbeat_time',
                'up.enable', 'up.usb_status',
                'uk.usb_uid', 'up.index AS port_index',
                'up.devcon_name',
            ])
            ->join('usb_port as up', 'ud.id', '=', 'up.usb_device_id')
            ->join('usb_key as uk', 'up.usb_uid', '=', 'uk.usb_uid')
            ->whereIn('ud.account_id', function($query) use($request) {
                $query->select('account_id')
                    ->from('usb_device')
                    ->where('hashcode', $request->input('hashcode'));
            })
            ->where('uk.usb_uid', $request->input('usb_uid'))
            ->where('ud.enable', '1')
            ->where('uk.key_status', '1')
            ->first();

        if (!$data) {
            return $this->presenter->json([], '无可用装置', 2);
        }

        // 检查时间
        $checkTime = Carbon::now()->subMinute(10);
        if (!$data->heartbeat_time || $checkTime->greaterThan($data->heartbeat_time)) {
            return $this->presenter->json([], '设备离线，超过10分钟未回报。', 2);
        }

        // 检查状态
        if ($data->usb_status == 0 && $data->enable == 1) {
            $returnData = [
                'hashcode' => $data->hashcode,
                'ip' => $data->ip,
                'usb_uid' => $data->usb_uid,
                'port_index' => $data->port_index,
            ];
            return $this->presenter->json($returnData, '成功', 1);
        } else {
            $msg = "";
            if ($data->usb_status == 1) {
                $msg .= "U盾异常。";
            } elseif ($data->usb_status == 2) {
                $msg .= "U盾已有其它交易使用，" . $data->devcon_name;
            } elseif ($data->usb_status == -1) {
                $msg .= "U盾异常！";
            }
            if ($data->enable == 0) {
                if ($msg != "") {
                    $msg .= ", ";
                }
                $msg .= "侦测不到U盾";
            }

            return $this->presenter->json([], $msg, 2);
        }

        return $this->presenter->json([], '无可用装置', 2);
    }

    /**
     * 设备回报
     *
     * db query 流程
     * 1. 捞usb_device
     * 2. 捞usb_port
     * 3. 若状态异常 或 恢复 捞商户 (0~1)
     * 4. 更新 port 状态有变更 跑n个 (n:0~16)
     * 5. 更新 device (heartbeat_time, ip)
     *     5-1. usb_uid有更新，取消别人的usb_uid
     * 6. 特别的U盾:
     *     6-1. 捞出特别autorun_device
     *     6-2. 更新 usb_port (autorun_id, autorun_change_time)
     *     6-3. 更新 autorun_job by (hashcode, 脚位)
    */
    public function update(Request $request)
    {
        Log::channel('devices')->info('[update] 接收资料: ' . json_encode($request->input(), 320));
        $this->validate($request, [
            'hashcode' => 'required',
            'ip' => 'required',
            'usb_list' => 'filled|array',
            'usb_list.*.index' => 'required',
            'usb_list.*.usb_status' => 'required',
            'usb_list.*.enable' => 'required',
            'usb_list.*.usb_uid' => 'present',
        ]);

        $hashcode = $request->input('hashcode');
        $usb_device_id = DB::table('usb_device')
            ->select(['id'])
            ->where('hashcode', $hashcode)
            ->first();

        if (!$usb_device_id) {
            $msg = sprintf('设备回报异常hashcode不存在[%s]', $hashcode);
            Log::channel('csharp_slack')->critical($msg);
            return $this->presenter->json([], "hashcode不存在", 2);
        }
        $id = $usb_device_id->id;
        $heartbeat_time = Carbon::now();

        // update port
        if ($request->filled('usb_list')) {

            // 宣告 特殊U盾跑的autorun, 避免重复 query
            $autorunList = [];
            // U盾全部异常 预设值
            $allFailed = true;
            // 转换带入参数 usb_list
            $newInput = [];
            foreach ($request->input('usb_list') as $val) {
                $newInput[$val['index']] = $val;
                // 检查是否全部异常
                if ($val['usb_status'] != -1) {
                    $allFailed = false;
                }
            }

            // 取得目前全部 port 资料
            $usb_port = DB::table('usb_port')
               ->where('usb_device_id', $id)
               ->get();

            // 异常通知
            $this->notifyStatus($allFailed, $hashcode, $usb_port);

            // 全部port 检查是否异动
            foreach ($usb_port as $obj) {
                $port = $obj->index;
                if (!isset($newInput[$port])) {
                    continue;
                }

                $usb_status = $newInput[$port]['usb_status'];
                $enable = $newInput[$port]['enable'];
                $usb_uid = empty($newInput[$port]['usb_uid']) ? null : $newInput[$port]['usb_uid'];
                $devcon_name = $newInput[$port]['devcon_name'] ?? null;
                $vid = $newInput[$port]['vid'] ?? null;

                if ($usb_uid != null && !Functions::verifyUidCode($usb_uid)) {
                    $msg = sprintf("设备[%s], 脚位[%s], uid[%s] 验证异常", $hashcode, $port, $usb_uid);
                    Log::channel('devices')->info('[update] uid异常: ' . $msg);
                    continue;
                }

                if (($usb_status != $obj->usb_status) ||
                    ($enable != $obj->enable) ||
                    ($usb_uid != $obj->usb_uid) ||
                    ($devcon_name != $obj->devcon_name) ||
                    ($vid != $obj->vid)
                ) {
                    // 有更新 usb_uid，取消其它装置的此usb_uid
                    if ($usb_uid != $obj->usb_uid && $usb_uid != null) {
                        DB::table('usb_port')
                            ->where('usb_uid', $usb_uid)
                            ->update([
                                'usb_uid' => null,
                            ]);
                    }

                    DB::table('usb_port')
                        ->where('usb_device_id', $id)
                        ->where('id', $obj->id)
                        ->update([
                            'usb_status' => $usb_status,
                            'enable' => $enable,
                            'usb_uid' => $usb_uid,
                            'devcon_name' => $devcon_name,
                            'vid' => $vid,
                        ]);
                }
                if ($vid && $usb_status != -1) {
                    $this->updateSpecialUKey($vid, $obj, $hashcode, $autorunList);
                }
            }
        }

        $updateData = [
            'ip' => $request->input('ip'),
            'heartbeat_time' => $heartbeat_time,
        ];

        if($request->filled('version')) {
            $updateData['version'] = $request->input('version');
        }

        // update usb_device
        DB::table('usb_device')
            ->where('id', $id)
            ->where('hashcode', $request->input('hashcode'))
            ->update($updateData);

        return $this->presenter->json([], "成功", 1);
    }

    // 设备更新中
    public function updating(Request $request)
    {
        $this->validate($request, [
            'hashcode' => 'required',
        ]);

        $hashcode = $request->input('hashcode');

        $update = DB::table('usb_device')
            ->where('hashcode', $hashcode)
            ->update(['version' => 'updating']);
        if ($update) {
            $account = $this->getAccountByDevHash($hashcode);
            if ($account->telegram_path && $account->telegram_chatid) {
                $msg = sprintf('设备[%s] 更新中', $hashcode);
                NotificationHelper::telegramSendmsg($msg, $account->telegram_path, $account->telegram_chatid);
                error_log($msg);
                error_log($account->telegram_path);
            }
        }
        return $this->presenter->json([], "成功", 1);
    }

    private function getAccountByDevHash($hashcode)
    {
        // 获取商户资料
        $account = DB::table('usb_device AS ud')
            ->select(['a.account', 'a.telegram_path', 'a.telegram_chatid'])
            ->join('account AS a', 'ud.account_id', '=', 'a.id')
            ->where('hashcode', $hashcode)
            ->first();
        return $account;
    }

    private function updateSpecialUKey($vid, $currentObj, $hashcode, &$autorunList)
    {
        $specialList = [
            '1EA8' => 'ming'
        ];
        if (!isset($specialList[$vid])) {
            return false;
        }

        $name = $specialList[$vid];
        if (!isset($autorunList[$name])) {
            $autorunList[$name] = DB::table('autorun_device')
                ->select(['id', 'dev_id'])
                ->where('enable', 1)
                ->where('dev_id', 'like', $name.'_%')
                ->get()
                ->keyBy('id')
                ->all();
        }

        $tempAutorun = $autorunList[$name];
        // 没有 特别的 autorun 可以跑
        if (empty($tempAutorun)) {
            return false;
        }

        $tempAutorunKeys = array_keys($tempAutorun);
        $pickKey = array_rand($tempAutorun, 1);
        $tempAutorun = $tempAutorun[$pickKey];

        // 更新port 与 autorun
        $time = Carbon::now();
        $updateBool = DB::table('usb_key')
            ->where('usb_uid', $currentObj->usb_uid)
            ->whereNotIn('autorun_id', $tempAutorunKeys)
            ->limit(1)
            ->update([
                'autorun_id' => $tempAutorun->id,
                'autorun_change_time' => $time
            ]);

        if ($updateBool) {
            DB::table('autorun_job')
                ->where([
                    'usb_uid' => $currentObj->usb_uid,
                    'usb_device_hashcode' => $hashcode,
                    'status' => 0
                ])
                ->update([
                    'dev_id' => $tempAutorun->dev_id,
                    'autorun_change_time' => $time
                ]);
        }

    }

    private function notifyStatus($allFailed, $hashcode, $usb_port)
    {
        if ($allFailed) {
            $account = $this->getAccountByDevHash($hashcode);
            if (!$account) {
                return false;
            }

            $msg = sprintf('[设备异常回报] 商户:%s，设备[%s] 异常', $account->account, $hashcode);

            DB::table('device_log')->insert([
                'account' => $account->account,
                'hashcode' => $hashcode,
                'type' => 1,
                'msg' => $msg
            ]);

            Log::channel('csharp_slack')->critical($msg);
            // 设备异常时 先不通知客户，由运维通知 2019-04-02 Jordan
            /*
            if ($account->telegram_path && $account->telegram_chatid) {
                NotificationHelper::telegramSendmsg($msg, $account->telegram_path, $account->telegram_chatid);
            }
            */
        } else {
            // 原有设备 全部异常，恢复时 发通知
            $dbPortAllFailed = true;
            foreach ($usb_port as $obj) {
                if ($obj->usb_status != -1) {
                    $dbPortAllFailed = false;
                    break;
                }
            }

            if ($dbPortAllFailed) {
                $account = $this->getAccountByDevHash($hashcode);
                if (!$account) {
                    return false;
                }
                $msg = sprintf('[设备回复回报] 商户:%s，设备[%s] 恢复', $account->account, $hashcode);
                Log::channel('csharp_slack')->critical($msg);
            }
        }
    }
}
