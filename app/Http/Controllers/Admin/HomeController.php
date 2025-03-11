<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Libraries\Functions;
use Illuminate\Http\Request;
use DB;


class HomeController extends AdminController
{
    /**
     * 登入记录
     */
    public function loginRecord(Request $request)
    {
        $result = DB::table('login_log')
            ->where(['account' => $request->session()->get('account')])
            ->orderBy('user_login_time','desc')
            ->limit(2)
            ->get();
        if(!$result){
            return $this->presenter->json([], "没有记录", 2);
        }

        return $this->presenter->json($result, "成功", 1);
    }

    /**
     * 交易统计
     */
    public function transCalc(Request $request)
    {
        // 历史纪录
        $schedule_db = DB::table('autorun_job_schedule AS ajs')
            ->join('account AS a', 'ajs.account_id', '=', 'a.id')
            ->selectRaw('a.account, ajs.suc_amount, ajs.fail_amount, ajs.suc_num, ajs.fail_num')
            ->whereDate('ajs.job_date', Carbon::yesterday()->toDateString());

        // 实时纪录
        $data_db = DB::table('autorun_job as aj')
            ->join('account AS a', 'aj.account_id', '=', 'a.id')
            ->selectRaw('SUM(IF(aj.status = 1, 1,0)) to_suc_num,
                SUM(IF(aj.status = 2, 1,0)) to_fail_num,
                SUM(IF(aj.status = 1, amount,0)) to_suc_amount,
                SUM(IF(aj.status = 2, amount,0)) to_fail_amount,
                a.account')
            ->whereDate('aj.success_at', Carbon::now()->toDateString())
            ->groupBy('aj.account_id');
        $new_array = [];
        if (!Functions::isAdmin($request)) {
            $account = $request->session()->get('topAccount');
            $new_array = [$account];
            $account_id = $request->session()->get('topAccountId');
            $data_db->where('aj.account_id', $account_id);
            $schedule_db->where('ajs.account_id', $account_id);
        }
        $data = $data_db->get()->keyBy('account')->toArray();
        $schedule_data = $schedule_db->get()->keyBy('account')->toArray();

        // 初始化回传阵列

        $new_data = array();
        $i = 0;
        // 取得所有商户号
        $new_array = array_unique(array_merge(array_keys($data), array_keys($schedule_data), $new_array));

        foreach ($new_array as $v) {
            // 设定预设值 0
            $temp = array_fill_keys([
                'suc_amount', 'fail_amount', 'suc_num', 'fail_num',
                'to_suc_num', 'to_fail_num', 'to_suc_amount', 'to_fail_amount',
            ], 0);
            $temp['account'] = $v;

            if(isset($data[$v])){
                $temp['to_suc_amount'] = round($data[$v]->to_suc_amount, 2);
                $temp['to_fail_amount'] = round($data[$v]->to_fail_amount, 2);
                $temp['to_suc_num'] = $data[$v]->to_suc_num;
                $temp['to_fail_num'] = $data[$v]->to_fail_num;
            }
            if(isset($schedule_data[$v])){
                $temp['suc_amount'] = round($schedule_data[$v]->suc_amount, 2);
                $temp['fail_amount'] = round($schedule_data[$v]->fail_amount, 2);
                $temp['suc_num'] = $schedule_data[$v]->suc_num;
                $temp['fail_num'] = $schedule_data[$v]->fail_num;
            }
            $new_data[$i++] = $temp;
        }
        return $this->presenter->json($new_data, "成功", 1);
    }

    /**
     * 设备状态
     */
    public function devicesStatus(Request $request)
    {
        $port = DB::table('usb_port')
            ->select('usb_device_id', DB::raw('count(*) as port'))
            ->whereIn('usb_uid', function ($query) use ($request) {
                $query->select('usb_uid')
                    ->from('usb_key')
                    ->whereNotNull('usb_uid')
                    ->where('account_id', $request->session()->get('topAccountId'));
            })
            ->where('enable' , 0)
            ->groupBy('usb_device_id');

        //新增银行卡时，只能选择自己的设备
        $data = DB::table('usb_device as ud')
            ->select(['ud.nickname', 'a.port', 'heartbeat_time', 'hashcode'])
            ->leftJoinSub($port, 'a', function ($join) {
                $join->on('ud.id', '=', 'a.usb_device_id');
            })
            ->where('ud.account_id', $request->session()->get('topAccountId'))
            ->where('ud.enable', 1);

        $result = $data->get();
        return $this->presenter->json($result, "成功", 1);
    }

    /**
     * 最新消息
     */
    public function newsMsg(Request $request)
    {
        $msg = DB::table('news_msg');
        if (!$request->filled('past')) {
            $msg->whereDate('created_at', '>', Carbon::now()->subDays(3));
        }
        $msg = $msg->orderBy('created_at','desc')->whereNull('deleted_at')->get();
        return $this->presenter->json($msg, "成功", 1);
    }


}
