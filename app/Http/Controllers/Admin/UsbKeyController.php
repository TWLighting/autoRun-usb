<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Libraries\Functions;
use DB;
use Log;

class UsbKeyController extends AdminController
{
    public function getList(Request $request)
    {
        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }

        $data = DB::table('usb_key AS uk')
            ->select([
                'uk.*', 'uk.id AS key_id', 'up.index AS up_i',
                'ud.hashcode AS ud_h', 'ud.nickname AS ud_name',
            ])
            ->where('uk.account_id', $request->session()->get('topAccountId'))
            ->leftjoin('usb_port AS up', 'up.usb_uid', '=', 'uk.usb_uid')
            ->leftjoin('usb_device AS ud', 'ud.id', '=', 'up.usb_device_id')
            ->orderBy('uk.id', 'desc');

        if ($request->filled('name')) {
            $data->where('uk.name', 'like', '%' . $request->input('name') . '%');
        }

        if ($request->filled('key_status')) {
            $data->where('uk.key_status', '=', $request->input('key_status'));
        }

        $result = $data->paginate($request->input('perpage', 20));

        return $this->presenter->json($result, "成功", 1);
    }

    public function create(Request $request)
    {
        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }

        $this->validate($request, [
            'name' => 'required',
        ]);

        $topAccountId = $request->session()->get('topAccountId');
        $autorun_id = 0;

        // 管理员帮商户加
        if (Functions::isAdmin($request)) {
            $account_id = $request->input('account_id', '');
            if (!empty($account_id)) {
                $topAccountId = $account_id;
            }
        }

        // 同商户下 U盾名称不能重复
        if ($this->checkUsbKeyExist($request->input('name'), $topAccountId)) {
            return $this->presenter->json([], 'U盾名称不能重复', 2);
        }

        //　若u盾新增时直接绑定uid 需做检查
        $usb_uid = $request->input('usb_uid', null);
        if ($usb_uid) {

            $autorun = $this->getAutorunByUid($usb_uid);
            if (!$autorun) {
                return $this->presenter->json([], '装置不存在', 2);
            }
            $autorun_id = $autorun->autorun_id;

            $countJob = $this->checkBankcardFree($usb_uid);
            if ($countJob > 0) {
                $msg = sprintf('新增失敗，原U盾还有 %s 项任务', $countJob);
                return $this->presenter->json([], $msg, 2);
            }

            DB::table('usb_key')
                ->where(['usb_uid' => $usb_uid])
                ->update(['usb_uid' => null]);
        }

        // 新增
        $insert_data = [
            'name' => $request->input('name'),
            'account_id' => $topAccountId,
            'key_status' => '1',
            'usb_uid' => $usb_uid,
            'autorun_id' => $autorun_id
        ];

        $last_id = DB::table('usb_key')->insertGetId($insert_data);
        if (!$last_id) {
            return $this->presenter->json([], '新增失敗', 2);
        }

        return $this->presenter->json($last_id, '新增成功', 1);
    }

    // port 配置 U盾
    public function update_port(Request $request)
    {

        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }
        $this->validate($request, [
            'usb_uid' => 'required',
            'key_id' => 'present'
        ]);

        $usb_uid = $request->input('usb_uid');
        $key_id = $request->input('key_id', null);

        $countJob = $this->checkBankcardFree($usb_uid);
        if ($countJob > 0) {
            $msg = sprintf('更新失敗，此U盾还有 %s 项任务', $countJob);
            return $this->presenter->json([], $msg, 2);
        }

        DB::table('usb_key')
            ->where(['usb_uid' => $usb_uid])
            ->update(['usb_uid' => NULL]);

        if ($key_id) {
            $usb_key = DB::table('usb_key')->where('id', $key_id);
            if (!Functions::isAdmin($request)) {
                $usb_key->where('account_id', $request->session()->get('topAccountId'));
            }

            $usb_key = $usb_key->first();

            if (!$usb_key) {
                return $this->presenter->json([], '更新失敗', 2);
            }

            $update = ['usb_uid' => $usb_uid];
            // 若没有配置 autorun_id 则使用设备预设的
            if ($usb_key->autorun_id == 0) {
                $autorun = $this->getAutorunByUid($usb_uid);
                if (!$autorun) {
                    return $this->presenter->json([], '装置不存在', 2);
                }

                $update['autorun_id'] = $autorun->autorun_id;
            }

            $result = DB::table('usb_key')
                ->where('id', $key_id)
                ->update($update);
            if (!$result) {
                return $this->presenter->json([], '更新失敗', 2);
            }
        }

        return $this->presenter->json([], '更新成功', 1);
    }

    // 修改U盾
    public function update_key(Request $request)
    {
        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }

        $this->validate($request, [
            'id' => 'required',
            'name' => 'required'
        ]);

        $input_data = $request->input();
        $topAccountId = $request->session()->get('topAccountId');

        if ($this->checkUsbKeyExist($input_data['name'], $topAccountId, $input_data['id'])) {
            return $this->presenter->json([], 'U盾名称不能重复', 2);
        }

        $result = DB::table('usb_key')
            ->where([
                'id' => $input_data['id'],
                'account_id' => $topAccountId
            ])
            ->update([
                'name' => $input_data['name'],
                'updated_at' => DB::raw('NOW()')
            ]);


        if (!$result) {
            return $this->presenter->json($result, '更新失敗', 2);
        }

        return $this->presenter->json($result, '更新成功', 1);
    }

    public function changeStatus(Request $request)
    {
        if ($request->session()->get('permission') != 1) {
            return $this->presenter->json([], "权限不足", 2);
        }

        $this->validate($request, [
            'id' => 'required',
            'key_status' => [
                'required',
                Rule::in([0, 1]),
            ],
        ]);

        $id = $request->input('id');
        $enable = $request->input('key_status');
        $update = ['key_status' => $enable];
        if ($enable == 0) {
            $update['usb_uid'] = null;
        }
        DB::table('usb_key')
            ->where('id', $id)
            ->update($update);
        return $this->presenter->json([], "成功", 1);
    }

    public function getUsbKey(Request $request)
    {
        $data = DB::table('usb_key')
            ->select(['id', 'name', 'usb_uid'])
            ->orderBy('id', 'asc');

        if (Functions::isAdmin($request)) {
            if ($request->filled('hashcode')) {
                $usb_device = DB::table('usb_device')
                    ->select(['account_id'])
                    ->where('hashcode', $request->input('hashcode'))
                    ->first();
                if ($usb_device) {
                    $data->where('account_id', $usb_device->account_id);
                }
            } else if ($request->filled('target_account_id')) {
                // 透过参数 取得某个商户 usb key
                $data->where('account_id', $request->input('target_account_id'));
            } else {
                $data->where('account_id', $request->session()->get('topAccountId'));
            }
        } else {
            $data->where('account_id', $request->session()->get('topAccountId'))->get();
        }
        $data = $data->where('key_status', '1')->get();
        return $this->presenter->json($data, "成功", 1);
    }

    public function clearUsbUid(Request $request)
    {
        $this->validate($request, [
            'usb_uid' => 'required',
        ]);

        $usb_uid = $request->input('usb_uid');
        $force = boolval($request->input('force', false));

        $countJob = $this->checkBankcardFree($usb_uid);
        if ($countJob > 0) {
            // 如果是强制解除绑定，更新订单
            if ($force) {
                DB::table('autorun_job')
                    ->where('usb_uid', $usb_uid)
                    ->where('status', 0)
                    ->update([
                        'status' => -1,
                        'attach' => 'U盾强制解除绑定',
                    ]);
            } else {
                $msg = sprintf('更新失敗，此U盾还有 %s 项任务', $countJob);
                return $this->presenter->json([], $msg, 2);
            }
        }

        $result = DB::table('usb_key');
        if (!Functions::isAdmin($request)) {
            $result->where('account_id', $request->session()->get('topAccountId'));
        }

        $result->where(['usb_uid' => $usb_uid])
            ->update(['usb_uid' => NULL]);

        if (!$result) {
            return $this->presenter->json([], '解除绑定失敗', 2);
        }

        return $this->presenter->json([], '解除绑定成功', 1);
    }

    // 检查名称是否重复
    private function checkUsbKeyExist($name, $account_id, $id = 0)
    {
        $data = DB::table('usb_key')
            ->select(['id'])
            ->where('name', $name)
            ->where('account_id', $account_id)
            ->where('id', '!=', $id)
            ->first();
        if ($data) {
            return true;
        }
        return false;
    }

    // port + usb_uid 里的 检查银行卡是否还有任务
    private function checkBankcardFree($usb_uid)
    {
        $ukeyJobs = DB::table('usb_key AS uk')
            ->selectRaw('count(*) as total')
            ->join('bank_card_info AS bci', 'bci.usb_key_id', '=', 'uk.id')
            ->join('autorun_job AS aj', 'aj.card_no', '=', 'bci.card_no')
            ->where('uk.usb_uid', $usb_uid)
            ->where('aj.status', 0)
            ->first();

        return $ukeyJobs->total;
    }

    private function getAutorunByUid($usb_uid)
    {
        return DB::table('usb_device AS ud')
            ->select(['ud.autorun_id'])
            ->join('usb_port as up', 'up.usb_device_id', '=', 'ud.id')
            ->where(['up.usb_uid' => $usb_uid])
            ->first();
    }
}